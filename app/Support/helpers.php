<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        static $values = null;
        if ($values === null) {
            $values = [];
            $file = base_path('.env');
            if (is_file($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    if (strlen($v) >= 2 && (($v[0] === '"' && str_ends_with($v, '"')) || ($v[0] === "'" && str_ends_with($v, "'")))) {
                        $v = substr($v, 1, -1);
                    }
                    $values[$k] = $v;
                }
            }
        }
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            $value = $values[$key] ?? null;
        }
        if ($value === null || $value === '') {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        if ($path === '') {
            return $base;
        }
        if (preg_match('#^(?:/|[A-Za-z]:[\\\\/])#', $path)) {
            return $path;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('db')) {
    function db(): PDO
    {
        return Database::connection();
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path === '/') {
            return '/';
        }
        return $path;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $rel = 'public/assets/' . ltrim($path, '/');
        $file = base_path($rel);
        $version = is_file($file) ? substr((string) filemtime($file), -6) : '1';
        return '/assets/' . ltrim($path, '/') . '?v=' . $version;
    }
}

if (!function_exists('session')) {
    function session(): Session
    {
        return new Session();
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(24));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('now_utc')) {
    function now_utc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('fmt_number')) {
    function fmt_number(mixed $n): string
    {
        return number_format((float) $n);
    }
}

if (!function_exists('fmt_datetime')) {
    function fmt_datetime(?string $value, string $fallback = '—'): string
    {
        if (!$value) return $fallback;
        try {
            $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $dt->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('m-d H:i');
        } catch (Throwable) {
            return $fallback;
        }
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(?string $value, string $fallback = '—'): string
    {
        if (!$value) return $fallback;
        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (Throwable) {
            return $fallback;
        }
    }
}

if (!function_exists('status_meta')) {
    function status_meta(string $status): array
    {
        return match ($status) {
            'completed' => ['label' => '已完成', 'tone' => 'success', 'dot' => 'bg-emerald-400', 'en' => 'COMPLETED'],
            'running' => ['label' => '运行中', 'tone' => 'info', 'dot' => 'bg-cyan-400', 'en' => 'RUNNING'],
            'submitted' => ['label' => '已提交', 'tone' => 'info', 'dot' => 'bg-sky-400', 'en' => 'SUBMITTED'],
            'submitting' => ['label' => '提交中', 'tone' => 'info', 'dot' => 'bg-sky-400', 'en' => 'SUBMITTING'],
            'queued' => ['label' => '等待提交', 'tone' => 'warning', 'dot' => 'bg-amber-400', 'en' => 'WAITING'],
            'draft' => ['label' => '草稿', 'tone' => 'muted', 'dot' => 'bg-slate-500', 'en' => 'DRAFT'],
            'partial' => ['label' => '部分导入', 'tone' => 'warning', 'dot' => 'bg-amber-400', 'en' => 'PARTIAL'],
            'empty' => ['label' => '空结果', 'tone' => 'warning', 'dot' => 'bg-amber-400', 'en' => 'EMPTY'],
            'failed' => ['label' => '失败', 'tone' => 'danger', 'dot' => 'bg-rose-400', 'en' => 'FAILED'],
            'cancelled' => ['label' => '已取消', 'tone' => 'muted', 'dot' => 'bg-slate-500', 'en' => 'CANCELLED'],
            'submission_unknown' => ['label' => '提交未知', 'tone' => 'danger', 'dot' => 'bg-rose-400', 'en' => 'UNKNOWN'],
            'ok' => ['label' => '外部完成', 'tone' => 'success', 'dot' => 'bg-emerald-400', 'en' => 'OK'],
            default => ['label' => $status ?: '未知', 'tone' => 'muted', 'dot' => 'bg-slate-500', 'en' => strtoupper($status ?: 'UNKNOWN')],
        };
    }
}

if (!function_exists('email_status_meta')) {
    function email_status_meta(string $status): array
    {
        return match ($status) {
            'found' => ['label' => '已发现', 'tone' => 'success'],
            'none_found' => ['label' => '未发现', 'tone' => 'muted'],
            'failed' => ['label' => '抓取失败', 'tone' => 'danger'],
            'blocked' => ['label' => '已阻止', 'tone' => 'warning'],
            default => ['label' => '未检查', 'tone' => 'muted'],
        };
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') return 'GB';
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $name, $m)) {
            return mb_substr($name, 0, 2);
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        $out = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $out ?: 'GB';
    }
}

if (!function_exists('flag_emoji')) {
    function flag_emoji(?string $iso2): string
    {
        $iso2 = strtoupper(trim((string) $iso2));
        if (strlen($iso2) !== 2) return '◈';
        return mb_chr(ord($iso2[0]) - 65 + 0x1F1E6) . mb_chr(ord($iso2[1]) - 65 + 0x1F1E6);
    }
}

if (!function_exists('safe_csv_cell')) {
    function safe_csv_cell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }
        return $value;
    }
}

if (!function_exists('pull_flashes')) {
    function pull_flashes(): array
    {
        $items = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        if (!is_array($items)) return [];
        return array_values(array_filter($items, 'is_array'));
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return \App\Core\Auth::user();
    }
}

if (!function_exists('auth_id')) {
    function auth_id(): int
    {
        return \App\Core\Auth::id();
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return \App\Core\Auth::check();
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return \App\Core\Auth::isAdmin();
    }
}

if (!function_exists('audit')) {
    function audit(string $action, ?string $entityType = null, ?string $entityId = null, array $context = []): void
    {
        try {
            $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, context_json, created_at) VALUES (?,?,?,?,?,?)');
            $stmt->execute([auth_id() ?: null, $action, $entityType, $entityId, json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), now_utc()]);
        } catch (Throwable) {
            // Audit logging must never break the primary action.
        }
    }
}

if (!function_exists('scraper_status_cache')) {
    function scraper_status_cache(): array
    {
        $file = base_path('storage/cache/scraper-status.json');
        if (!is_file($file)) {
            return ['state' => 'unknown', 'checked_at' => null, 'latency_ms' => null, 'message' => '尚未检测'];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : ['state' => 'unknown', 'checked_at' => null, 'latency_ms' => null, 'message' => '无缓存'];
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $m = status_meta($status);
        $tones = [
            'success' => 'border-emerald-400/25 bg-emerald-400/10 text-emerald-300',
            'info' => 'border-cyan-400/25 bg-cyan-400/10 text-cyan-300',
            'warning' => 'border-amber-400/25 bg-amber-400/10 text-amber-300',
            'danger' => 'border-rose-400/25 bg-rose-400/10 text-rose-300',
            'muted' => 'border-white/10 bg-white/5 text-slate-400',
        ];
        return '<span class="status-badge ' . ($tones[$m['tone']] ?? $tones['muted']) . '"><span class="h-1.5 w-1.5 rounded-full ' . $m['dot'] . '"></span>' . e($m['label']) . '</span>';
    }
}

if (!function_exists('language_name')) {
    function language_name(?string $code): string
    {
        $code = trim((string) $code);
        if ($code === '') return '未知';
        $map = [
            'en' => '英语', 'zh' => '中文', 'ja' => '日语', 'de' => '德语',
            'fr' => '法语', 'es' => '西班牙语', 'pt' => '葡萄牙语', 'it' => '意大利语',
            'nl' => '荷兰语', 'ar' => '阿拉伯语', 'ko' => '韩语', 'ru' => '俄语',
            'pl' => '波兰语', 'tr' => '土耳其语', 'id' => '印度尼西亚语', 'th' => '泰语',
            'vi' => '越南语', 'hi' => '印地语', 'uk' => '乌克兰语', 'ro' => '罗马尼亚语',
            'cs' => '捷克语', 'el' => '希腊语', 'he' => '希伯来语', 'sv' => '瑞典语',
        ];
        $key = strtolower($code);
        if (isset($map[$key])) return $map[$key];
        if (class_exists('Locale')) {
            try {
                $display = \Locale::getDisplayLanguage($key, 'zh_CN');
                if (is_string($display) && $display !== '' && strtolower($display) !== $key) {
                    return $display;
                }
            } catch (Throwable) {}
        }
        return $code;
    }
}

if (!function_exists('external_status_label')) {
    function external_status_label(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'ok', 'done', 'completed', 'success', 'finished' => '外部已完成',
            'running', 'working', 'processing' => '外部运行中',
            'queued', 'pending' => '外部排队中',
            'failed', 'error' => '外部失败',
            'cancelled', 'canceled' => '外部已取消',
            'submitted' => '已提交',
            'deleted_after_import' => '导入后已删除外部任务',
            'delete_after_import_failed' => '外部任务删除失败',
            '' => '未同步',
            default => (string) $status,
        };
    }
}

if (!function_exists('country_name_cn')) {
    function country_name_cn(?string $iso2, ?string $fallback = null): string
    {
        static $dbNames = null;
        $code = strtoupper(trim((string) $iso2));
        if ($dbNames === null) {
            $dbNames = [];
            try {
                foreach (db()->query('SELECT iso2, name_zh, name_en FROM countries') as $row) {
                    $dbNames[strtoupper((string) $row['iso2'])] = $row['name_zh'] ?: $row['name_en'];
                }
            } catch (Throwable) {
                $dbNames = [];
            }
        }
        if ($code !== '' && !empty($dbNames[$code])) {
            return $dbNames[$code];
        }
        $map = [
            'GB' => '英国', 'US' => '美国', 'CA' => '加拿大', 'AU' => '澳大利亚',
            'DE' => '德国', 'FR' => '法国', 'JP' => '日本', 'SG' => '新加坡',
            'AE' => '阿联酋', 'BR' => '巴西', 'IN' => '印度', 'NL' => '荷兰',
            'ES' => '西班牙', 'IT' => '意大利', 'NZ' => '新西兰', 'ZA' => '南非',
            'CN' => '中国', 'HK' => '中国香港', 'TW' => '中国台湾', 'KR' => '韩国',
        ];
        return $map[$code] ?? ($fallback ?: ($code ?: '未知国家'));
    }
}

if (!function_exists('industry_name_display')) {
    function industry_name_display(?int $id, ?string $fallback = null): string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                $rows = db()->query(
                    "SELECT i.id, i.name, t.name AS name_zh
                     FROM industries i
                     LEFT JOIN industry_translations t ON t.industry_id = i.id AND t.language_code = 'zh'"
                )->fetchAll();
                foreach ($rows as $row) {
                    $cache[(int) $row['id']] = $row['name_zh'] ?: $row['name'];
                }
            } catch (Throwable) {
                $cache = [];
            }
        }
        if ($id !== null && isset($cache[$id])) {
            return $cache[$id];
        }
        return $fallback ?: '未分类';
    }
}

if (!function_exists('place_name_cn')) {
    function place_name_cn(?string $name, ?string $fallback = null): string
    {
        $key = trim((string) $name);
        $map = [
            'London' => '伦敦', 'Manchester' => '曼彻斯特', 'Birmingham' => '伯明翰',
            'New York' => '纽约', 'Los Angeles' => '洛杉矶', 'Miami' => '迈阿密',
            'Toronto' => '多伦多', 'Vancouver' => '温哥华', 'Sydney' => '悉尼',
            'Melbourne' => '墨尔本', 'Berlin' => '柏林', 'Munich' => '慕尼黑',
            'Paris' => '巴黎', 'Lyon' => '里昂', 'Tokyo' => '东京', 'Osaka' => '大阪',
            'Singapore' => '新加坡', 'Dubai' => '迪拜', 'Abu Dhabi' => '阿布扎比',
            'São Paulo' => '圣保罗', 'Sao Paulo' => '圣保罗', 'Mumbai' => '孟买',
            'Amsterdam' => '阿姆斯特丹', 'Madrid' => '马德里', 'Rome' => '罗马',
            'Auckland' => '奥克兰', 'Cape Town' => '开普敦',
        ];
        if (isset($map[$key])) return $map[$key];
        static $localNames = null;
        if ($localNames === null) {
            $localNames = [];
            try {
                foreach (db()->query('SELECT name, name_local FROM regions') as $row) {
                    if (!empty($row['name_local'])) {
                        $localNames[mb_strtolower((string) $row['name'])] = $row['name_local'];
                    }
                }
            } catch (Throwable) {
                $localNames = [];
            }
        }
        $lookup = mb_strtolower($key);
        if ($lookup !== '' && !empty($localNames[$lookup])) {
            return $localNames[$lookup];
        }
        return $key !== '' ? $key : ($fallback ?: '未知地区');
    }
}

if (!function_exists('region_type_label')) {
    function region_type_label(?string $type): string
    {
        return match (strtolower(trim((string) $type))) {
            'state' => '州 / 省',
            'province' => '省',
            'county' => '县',
            'city' => '城市',
            'district' => '区',
            default => $type ?: '地区',
        };
    }
}

if (!function_exists('app_setting')) {
    function app_setting(string $key, mixed $default = null): mixed
    {
        static $cache = [];
        $userId = auth_id();
        $cacheKey = $userId . ':' . $key;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }
        try {
            if ($userId > 0) {
                $stmt = db()->prepare('SELECT value FROM user_settings WHERE user_id = ? AND `key` = ?');
                $stmt->execute([$userId, $key]);
                $value = $stmt->fetchColumn();
                if ($value !== false && $value !== null) {
                    return $cache[$cacheKey] = $value;
                }
            }
            $stmt = db()->prepare('SELECT value FROM app_settings WHERE `key` = ?');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            $cache[$cacheKey] = ($value === false || $value === null) ? $default : $value;
        } catch (Throwable) {
            $cache[$cacheKey] = $default;
        }
        return $cache[$cacheKey];
    }
}

if (!function_exists('recalculate_business_score')) {
    function recalculate_business_score(int $businessId, ?int $userId = null): int
    {
        $userId = $userId ?? auth_id();
        if ($userId <= 0) return 0;
        $stmt = db()->prepare('SELECT b.*, (SELECT COUNT(*) FROM business_contacts bc WHERE bc.business_id = b.id AND bc.contact_type = "email") AS email_count FROM businesses b WHERE b.id = ? AND b.user_id = ?');
        $stmt->execute([$businessId, $userId]);
        $b = $stmt->fetch();
        $stmt->closeCursor();
        if (!$b) return 0;
        $score = 0;
        if (!empty($b['business_name'])) $score += 20;
        if (!empty($b['country_id']) && (!empty($b['city']) || !empty($b['address']))) $score += 15;
        if (!empty($b['phone'])) $score += 15;
        if (!empty($b['website'])) $score += 15;
        if ((int) $b['email_count'] > 0 || $b['email_status'] === 'found') $score += 20;
        if ($b['latitude'] !== null && $b['longitude'] !== null) $score += 5;
        if ($b['rating'] !== null || $b['review_count'] !== null) $score += 5;
        if (!empty($b['industry_id'])) $score += 5;
        $score = min(100, $score);
        db()->prepare('UPDATE businesses SET completeness_score = ? WHERE id = ? AND user_id = ?')->execute([$score, $businessId, $userId]);
        return $score;
    }
}
