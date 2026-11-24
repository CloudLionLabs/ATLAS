<?php
declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use Throwable;

/**
 * Conservative public-website email discovery.
 *
 * Safety rules:
 * - only public HTTP(S) targets (private/reserved IPs and DNS rebinding blocked)
 * - robots.txt is checked before every crawl
 * - max 3 pages, 2 MB per response, 8 second timeout
 * - only explicit published addresses; no guessing or SMTP probing
 * - extracted addresses remain `unverified`
 */
final class WebsiteEmailFinder
{
    private const USER_AGENT = 'AtlasEmailFinder/1.0 (local business admin; contact-page discovery)';
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const MAX_REDIRECTS = 3;
    private const MAX_EMAILS = 20;

    private bool $enabled;
    private int $maxPages;
    private array $blacklist;

    public function __construct()
    {
        $this->enabled = (string) app_setting('email_finder_enabled', '0') === '1';
        $this->maxPages = max(1, min(5, (int) app_setting('email_finder_max_pages', '3')));
        $raw = (string) app_setting('email_domain_blacklist', '');
        $this->blacklist = array_values(array_filter(array_map(static function (string $d): string {
            $d = strtolower(trim($d));
            return preg_replace('#^https?://#', '', $d) ?: '';
        }, preg_split('/[\s,;]+/', $raw) ?: [])));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function findForBusiness(int $businessId): array
    {
        $stmt = db()->prepare('SELECT * FROM businesses WHERE id = ? AND user_id = ?');
        $stmt->execute([$businessId, auth_id()]);
        $business = $stmt->fetch();
        $stmt->closeCursor();
        if (!$business) {
            return ['ok' => false, 'status' => 'failed', 'message' => '商家不存在', 'emails' => [], 'pages' => 0];
        }
        if (!$this->enabled) {
            return ['ok' => false, 'status' => $business['email_status'], 'message' => '邮箱补全模块未启用', 'emails' => [], 'pages' => 0];
        }
        if (empty($business['website'])) {
            $this->setStatus($businessId, 'failed');
            return ['ok' => false, 'status' => 'failed', 'message' => '该商家没有官网，无法从官网补全邮箱', 'emails' => [], 'pages' => 0];
        }

        $startUrl = $this->normalizeWebsiteUrl((string) $business['website']);
        if ($startUrl === null) {
            $this->setStatus($businessId, 'failed');
            return ['ok' => false, 'status' => 'failed', 'message' => '官网地址格式无效', 'emails' => [], 'pages' => 0];
        }
        $host = strtolower((string) parse_url($startUrl, PHP_URL_HOST));
        if ($this->isBlacklisted($host)) {
            $this->setStatus($businessId, 'blocked');
            return ['ok' => false, 'status' => 'blocked', 'message' => '该官网域名在黑名单中，已跳过', 'emails' => [], 'pages' => 0];
        }

        $robots = $this->robotsAllowed($startUrl);
        if (!$robots) {
            $this->setStatus($businessId, 'blocked');
            audit('business.email_enrich.blocked', 'business', (string) $businessId, ['url' => $startUrl, 'reason' => 'robots']);
            return ['ok' => false, 'status' => 'blocked', 'message' => 'robots.txt 禁止抓取该页面', 'emails' => [], 'pages' => 0];
        }

        $home = $this->fetchFollow($startUrl, 'text/html');
        if (!$home['ok']) {
            $status = in_array($home['http_status'] ?? 0, [401, 403], true) ? 'blocked' : 'failed';
            $this->setStatus($businessId, $status);
            audit('business.email_enrich.' . $status, 'business', (string) $businessId, ['url' => $startUrl, 'error' => $home['error']]);
            return [
                'ok' => false,
                'status' => $status,
                'message' => $status === 'blocked' ? '官网拒绝访问（HTTP 401/403）' : ('官网访问失败：' . ($home['error'] ?? '未知错误')),
                'emails' => [],
                'pages' => 0,
            ];
        }

        $pages = [['url' => $home['final_url'] ?? $startUrl, 'html' => (string) $home['body']]];
        $contactLinks = $this->discoverContactLinks((string) $home['body'], (string) ($home['final_url'] ?? $startUrl));
        $fetched = 1;
        foreach ($contactLinks as $contactUrl) {
            if ($fetched >= $this->maxPages) break;
            if (!$this->robotsAllowed($contactUrl)) continue;
            usleep(450000);
            $page = $this->fetchFollow($contactUrl, 'text/html');
            $fetched++;
            if ($page['ok']) {
                $pages[] = ['url' => $page['final_url'] ?? $contactUrl, 'html' => (string) $page['body']];
            }
        }

        $emails = [];
        foreach ($pages as $page) {
            foreach (self::extractEmailsFromHtml($page['html']) as $email) {
                if (!isset($emails[$email])) {
                    $emails[$email] = $page['url'];
                }
            }
        }

        $existingStmt = db()->prepare("SELECT COUNT(*) FROM business_contacts WHERE business_id = ? AND contact_type = 'email'");
        $existingStmt->execute([$businessId]);
        $existing = (int) $existingStmt->fetchColumn();
        $existingStmt->closeCursor();

        if ($emails) {
            $this->storeEmails($businessId, $emails, $existing);
            $this->setStatus($businessId, 'found');
            $message = '发现 ' . count($emails) . ' 个公开邮箱（未验证）';
            $status = 'found';
        } else {
            $status = $existing > 0 ? 'found' : 'none_found';
            $this->setStatus($businessId, $status);
            $message = $existing > 0 ? '本次未发现新邮箱，已有邮箱保留' : '已检查公开页面，未发现邮箱';
        }

        recalculate_business_score($businessId);
        audit('business.email_enrich.' . $status, 'business', (string) $businessId, [
            'website' => $startUrl,
            'pages' => $fetched,
            'found' => array_keys($emails),
        ]);
        return [
            'ok' => true,
            'status' => $status,
            'message' => $message,
            'emails' => array_keys($emails),
            'pages' => $fetched,
        ];
    }

    public static function extractEmailsFromHtml(string $html): array
    {
        if ($html === '') return [];
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        // Only inspect visible text and explicit mailto links. Form placeholders,
        // scripts and style blocks commonly contain fake demo addresses.
        foreach ($xpath->query('//script|//style|//noscript|//template') ?: [] as $node) {
            if ($node->parentNode) $node->parentNode->removeChild($node);
        }
        $candidates = [];
        foreach ($xpath->query('//a[starts-with(translate(@href, "MAILTO", "mailto"), "mailto:")]') ?: [] as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));
            $href = preg_replace('/^mailto:/i', '', $href) ?? $href;
            $href = preg_split('/[?&]/', $href)[0] ?? $href;
            $candidates[] = rawurldecode($href);
        }
        // Join individual text nodes with spaces. Joining only visible nodes
        // avoids fake addresses from placeholders and avoids gluing adjacent
        // labels (e.g. phone + email + button) into invalid addresses.
        $textParts = [];
        foreach ($xpath->query('//body//text()') ?: [] as $textNode) {
            $value = trim((string) $textNode->nodeValue);
            if ($value !== '') $textParts[] = $value;
        }
        $text = html_entity_decode(implode(' ', $textParts), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s*(?:\[\s*at\s*\]|\(\s*at\s*\)|&#64;|&commat;)\s*/iu', '@', $text) ?? $text;
        $text = preg_replace('/\s*(?:\[\s*dot\s*\]|\(\s*dot\s*\))\s*/iu', '.', $text) ?? $text;
        $candidates[] = $text;
        $emails = [];
        $blockedDomains = ['example.com', 'example.org', 'example.net', 'domain.com', 'yourdomain.com', 'email.com', 'sentry.io', 'wixpress.com', 'sentry-next.wixpress.com'];
        $commonTlds = [
            'com', 'org', 'net', 'edu', 'gov', 'mil', 'io', 'co', 'biz', 'info', 'me', 'app', 'dev', 'ai', 'tech',
            'online', 'site', 'store', 'london', 'plumbing', 'services', 'agency', 'company', 'group', 'global',
            'digital', 'studio', 'design', 'works', 'solutions', 'email', 'uk', 'us', 'ca', 'au', 'de', 'fr', 'jp',
            'sg', 'ae', 'br', 'in', 'nl', 'es', 'it', 'nz', 'za', 'ie', 'eu', 'ch', 'at', 'be', 'se', 'no', 'dk',
            'fi', 'pl', 'pt', 'tr', 'mx', 'ar', 'cl', 'kr', 'hk', 'tw', 'th', 'my', 'id', 'ph', 'vn', 'eg', 'ng', 'ke',
        ];
        $commonPairs = ['co.uk', 'com.au', 'co.nz', 'co.jp', 'com.br', 'co.za', 'com.sg', 'co.in', 'com.mx', 'co.kr', 'com.hk', 'com.tw'];
        foreach ($candidates as $chunk) {
            preg_match_all('/(?<![A-Z0-9._%+\-])[A-Z][A-Z0-9._%+\-]{0,63}@(?:[A-Z0-9](?:[A-Z0-9\-]{0,61}[A-Z0-9])?\.)+[A-Z]{2,24}/iu', (string) $chunk, $matches);
            foreach ($matches[0] ?? [] as $candidate) {
                $candidate = strtolower(trim($candidate, " \t\n\r\0\x0B<>()[]\"'.,;:"));
                if ($candidate === '' || strlen($candidate) > 254 || !filter_var($candidate, FILTER_VALIDATE_EMAIL)) continue;
                [$local, $domain] = array_pad(explode('@', $candidate, 2), 2, '');
                if ($domain === '' || in_array($domain, $blockedDomains, true)) continue;
                if (str_starts_with($domain, 'example.') || str_ends_with($domain, '.example') || str_ends_with($domain, '.invalid') || str_ends_with($domain, '.test')) continue;
                if (preg_match('/\.(png|jpe?g|gif|webp|svg|ico|css|js)$/i', $candidate)) continue;
                if (preg_match('/^(no-?reply|donotreply|do-not-reply|example|test|yourname|your\.name|name|email|user|username|your-email|youremail)$/i', $local)) continue;
                $labels = explode('.', $domain);
                $tld = end($labels) ?: '';
                $second = count($labels) >= 2 ? $labels[count($labels) - 2] : '';
                $pair = $second . '.' . $tld;
                if (!in_array($tld, $commonTlds, true) && !in_array($pair, $commonPairs, true)) continue;
                $emails[$candidate] = $candidate;
                if (count($emails) >= self::MAX_EMAILS) break 2;
            }
        }
        return array_values($emails);
    }

    public static function isPublicIpAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
        if (stripos($ip, '::ffff:') === 0) {
            return self::isPublicIpAddress(substr($ip, 7));
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return false;
        $packed = @inet_pton($ip);
        if ($packed === false) return false;
        $blocked = [
            ['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8],
            ['169.254.0.0', 16], ['172.16.0.0', 12], ['192.0.0.0', 24], ['192.0.2.0', 24],
            ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24],
            ['224.0.0.0', 4], ['240.0.0.0', 4],
        ];
        if (strlen($packed) === 4) {
            $long = ip2long($ip);
            foreach ($blocked as [$network, $bits]) {
                $mask = $bits === 0 ? 0 : (~0 << (32 - $bits)) & 0xFFFFFFFF;
                if (($long & $mask) === (ip2long($network) & $mask)) return false;
            }
            return true;
        }
        foreach (['::1', '::', 'fe80::', 'fc00::', 'fd00::'] as $prefix) {
            $packedPrefix = @inet_pton($prefix);
            if ($packedPrefix !== false && str_starts_with($packed, $packedPrefix)) return false;
        }
        return true;
    }

    private function setStatus(int $businessId, string $status): void
    {
        db()->prepare('UPDATE businesses SET email_status = ?, updated_at = ? WHERE id = ? AND user_id = ?')->execute([$status, now_utc(), $businessId, auth_id()]);
    }

    private function storeEmails(int $businessId, array $emails, int $existing): void
    {
        $hasPrimary = false;
        $stmt = db()->prepare("SELECT COUNT(*) FROM business_contacts WHERE business_id = ? AND contact_type = 'email' AND is_primary = 1");
        $stmt->execute([$businessId]);
        $hasPrimary = (int) $stmt->fetchColumn() > 0;
        $stmt->closeCursor();
        $insert = db()->prepare("INSERT IGNORE INTO business_contacts (business_id, contact_type, contact_value, normalized_value, source_url, source_type, verification_status, is_primary, discovered_at) VALUES (?, 'email', ?, ?, ?, 'official_website', 'unverified', ?, ?)");
        foreach ($emails as $email => $source) {
            $primary = (!$hasPrimary && $existing === 0) ? 1 : 0;
            $insert->execute([$businessId, $email, $email, $source, $primary, now_utc()]);
            if ($primary) $hasPrimary = true;
            $existing++;
        }
    }

    private function normalizeWebsiteUrl(string $website): ?string
    {
        $website = trim($website);
        if ($website === '') return null;
        if (!preg_match('#^https?://#i', $website)) {
            $website = 'https://' . ltrim($website, '/');
        }
        $parts = parse_url($website);
        if (!$parts || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) return null;
        if (!empty($parts['user']) || !empty($parts['pass'])) return null;
        return $website;
    }

    private function isBlacklisted(string $host): bool
    {
        $host = strtolower($host);
        foreach ($this->blacklist as $entry) {
            if ($host === $entry || str_ends_with($host, '.' . $entry)) return true;
        }
        return false;
    }

    private function robotsAllowed(string $url): bool
    {
        static $cache = [];
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) return false;
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $cacheKey = $scheme . '://' . $host . $port;
        if (isset($cache[$cacheKey])) {
            return $this->pathAllowedByRobots((string) ($parts['path'] ?? '/'), $cache[$cacheKey]);
        }
        $robotsUrl = $cacheKey . '/robots.txt';
        $response = $this->request($robotsUrl, 0, 'text/plain, text/*;q=0.8, */*;q=0.1');
        if ($response['error'] !== null || $response['http_status'] === 0) {
            $cache[$cacheKey] = [];
            return true;
        }
        if (in_array($response['http_status'], [401, 403], true)) {
            return false;
        }
        if ($response['http_status'] !== 200) {
            $cache[$cacheKey] = [];
            return true;
        }
        $cache[$cacheKey] = $this->parseRobots((string) $response['body']);
        return $this->pathAllowedByRobots((string) ($parts['path'] ?? '/'), $cache[$cacheKey]);
    }

    private function parseRobots(string $body): array
    {
        $groups = [];
        $agents = [];
        $rules = [];
        $flush = static function () use (&$groups, &$agents, &$rules): void {
            if ($agents && $rules) {
                $groups[] = ['agents' => $agents, 'rules' => $rules];
            }
            $agents = [];
            $rules = [];
        };
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
            if ($line === '' || !str_contains($line, ':')) continue;
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            if ($key === 'user-agent') {
                if ($rules) $flush();
                $agents[] = strtolower($value);
            } elseif (in_array($key, ['allow', 'disallow'], true) && $agents) {
                $rules[] = ['type' => $key, 'path' => $value];
            }
        }
        $flush();
        $applicable = [];
        foreach ($groups as $group) {
            foreach ($group['agents'] as $agent) {
                if ($agent === '*' || str_contains(self::USER_AGENT, $agent) || str_contains($agent, 'atlasemailfinder')) {
                    $applicable = array_merge($applicable, $group['rules']);
                    break;
                }
            }
        }
        return $applicable;
    }

    private function pathAllowedByRobots(string $path, array $rules): bool
    {
        if ($path === '') $path = '/';
        $best = null;
        $bestLength = -1;
        $bestAllow = true;
        foreach ($rules as $rule) {
            $pattern = $rule['path'];
            if ($pattern === '') continue;
            $regex = preg_quote($pattern, '#');
            $regex = str_replace('\*', '.*', $regex);
            if (preg_match('#^' . $regex . '#i', $path)) {
                $length = strlen($pattern);
                if ($length > $bestLength) {
                    $bestLength = $length;
                    $bestAllow = $rule['type'] === 'allow';
                }
            }
        }
        return $bestAllow;
    }

    private function fetchFollow(string $url, string $accept, int $redirect = 0): array
    {
        $response = $this->request($url, $redirect, $accept);
        if ($response['error'] !== null) return $response;
        if (in_array($response['http_status'], [301, 302, 303, 307, 308], true) && !empty($response['location']) && $redirect < self::MAX_REDIRECTS) {
            $next = $this->absoluteUrl($response['location'], $url);
            if ($next === null || !$this->sameHost($url, $next)) {
                return ['ok' => false, 'error' => '重定向目标不在同一站点', 'http_status' => $response['http_status'], 'body' => '', 'final_url' => $url, 'content_type' => ''];
            }
            $nextResponse = $this->fetchFollow($next, $accept, $redirect + 1);
            if (($nextResponse['final_url'] ?? null) === null) $nextResponse['final_url'] = $next;
            return $nextResponse;
        }
        $contentType = strtolower((string) ($response['content_type'] ?? ''));
        $isHtml = str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml');
        if (!str_contains($accept, 'text/html') || $isHtml) {
            $response['ok'] = $response['http_status'] >= 200 && $response['http_status'] < 300;
            $response['final_url'] = $url;
        } else {
            $response['ok'] = false;
            $response['error'] = '官网返回非 HTML 内容';
            $response['final_url'] = $url;
        }
        return $response;
    }

    private function request(string $url, int $redirect = 0, string $accept = 'text/html,application/xhtml+xml'): array
    {
        $parsed = $this->validatePublicUrl($url);
        if (!$parsed['ok']) {
            return ['ok' => false, 'error' => $parsed['error'], 'http_status' => 0, 'body' => '', 'content_type' => '', 'location' => null];
        }
        $ch = curl_init($url);
        $body = '';
        $headers = [];
        $tooLarge = false;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$parsed['host'] . ':' . $parsed['port'] . ':' . $parsed['ip']],
            CURLOPT_HTTPHEADER => ['Accept: ' . $accept, 'Accept-Language: zh-CN,zh;q=0.9,en;q=0.7'],
            CURLOPT_ENCODING => '',
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($key))] = trim($value);
                }
                return $length;
            },
        ]);
        curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: ($headers['content-type'] ?? ''));
        $location = $headers['location'] ?? null;

        if ($tooLarge) {
            return ['ok' => false, 'error' => '页面超过 2 MB 限制', 'http_status' => $status, 'body' => '', 'content_type' => $contentType, 'location' => $location];
        }
        return [
            'ok' => $error === '',
            'error' => $error !== '' ? $error : null,
            'http_status' => $status,
            'body' => $body,
            'content_type' => $contentType,
            'location' => $location,
        ];
    }

    private function validatePublicUrl(string $url): array
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return ['ok' => false, 'error' => 'URL 无效'];
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['ok' => false, 'error' => '仅允许 HTTP(S) 地址'];
        }
        if (!empty($parts['user']) || !empty($parts['pass'])) {
            return ['ok' => false, 'error' => 'URL 不能包含认证信息'];
        }
        $host = strtolower($parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return ['ok' => false, 'error' => '禁止访问本机地址'];
        }
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            return ['ok' => false, 'error' => '端口无效'];
        }
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $ips = [$host];
        } else {
            $a = @dns_get_record($host, DNS_A);
            $aaaa = @dns_get_record($host, DNS_AAAA);
            foreach (($a ?: []) as $record) {
                if (!empty($record['ip'])) $ips[] = $record['ip'];
            }
            foreach (($aaaa ?: []) as $record) {
                if (!empty($record['ipv6'])) $ips[] = $record['ipv6'];
            }
            if (!$ips) {
                $resolved = gethostbyname($host);
                if ($resolved !== $host) $ips[] = $resolved;
            }
        }
        $public = [];
        foreach ($ips as $ip) {
            if (self::isPublicIpAddress($ip)) $public[] = $ip;
        }
        $ipv4 = array_values(array_filter($public, static fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false));
        if (!$ipv4) {
            return ['ok' => false, 'error' => '域名未解析到可访问的公网 IPv4 地址'];
        }
        return ['ok' => true, 'host' => $host, 'port' => $port, 'ip' => $ipv4[0]];
    }

    private function discoverContactLinks(string $html, string $baseUrl): array
    {
        if ($html === '') return [];
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $links = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));
            if ($href === '' || preg_match('/^(mailto:|tel:|javascript:|#)/i', $href)) continue;
            $url = $this->absoluteUrl($href, $baseUrl);
            if ($url === null || !$this->sameHost($baseUrl, $url)) continue;
            $text = mb_strtolower(trim($anchor->textContent . ' ' . $href));
            $score = 0;
            foreach (['contact' => 8, '联系' => 8, 'kontakt' => 7, 'impressum' => 6, 'about' => 4, '关于' => 4, 'legal' => 4, 'team' => 3, 'support' => 3, '帮助' => 3] as $word => $weight) {
                if (str_contains($text, $word)) $score += $weight;
            }
            if ($score === 0) continue;
            if (preg_match('/\.(pdf|zip|jpe?g|png|gif|webp|svg|mp4|mov)$/i', $url)) continue;
            if (!isset($links[$url]) || $links[$url] < $score) $links[$url] = $score;
        }
        arsort($links);
        return array_slice(array_keys($links), 0, 2);
    }

    private function absoluteUrl(string $href, string $base): ?string
    {
        if (preg_match('#^https?://#i', $href)) return $href;
        $parts = parse_url($base);
        if (!$parts || empty($parts['host'])) return null;
        $origin = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($href, '//')) return ($parts['scheme'] ?? 'https') . ':' . $href;
        if (str_starts_with($href, '/')) return $origin . $href;
        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');
        return $origin . ($dir === '' ? '' : $dir) . '/' . ltrim($href, '/');
    }

    private function sameHost(string $a, string $b): bool
    {
        $hostA = strtolower((string) parse_url($a, PHP_URL_HOST));
        $hostB = strtolower((string) parse_url($b, PHP_URL_HOST));
        $hostA = preg_replace('/^www\./', '', $hostA) ?? $hostA;
        $hostB = preg_replace('/^www\./', '', $hostB) ?? $hostB;
        return $hostA !== '' && $hostA === $hostB;
    }
}
