<?php
declare(strict_types=1);

namespace App\Modules\Admin\Settings\Controller;

use App\Core\Controller;
use App\Services\GeoCities;
use App\Services\ScraperClient;

final class SettingsController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(): string
    {
        $settings = (new \App\Models\Setting())->forUser($this->userId());
        $defaultRows = db()->query('SELECT * FROM app_settings ORDER BY `key`')->fetchAll();
        foreach ($defaultRows as $row) {
            if (!array_key_exists($row['key'], $settings)) $settings[$row['key']] = $row['value'];
        }
        $scraper = (new ScraperClient())->baseUrl();
        $status = scraper_status_cache();
        $countryCount = (int) db()->query('SELECT COUNT(*) FROM countries WHERE is_active = 1')->fetchColumn();
        $industryCount = (int) db()->query('SELECT COUNT(*) FROM industries WHERE is_active = 1')->fetchColumn();
        $keywordCountStmt = db()->prepare('SELECT COUNT(*) FROM keywords WHERE user_id = ? AND is_active = 1');
        $keywordCountStmt->execute([$this->userId()]);
        $keywordCount = (int) $keywordCountStmt->fetchColumn();
        $dbSize = (int) round((float) (db()->query("SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn() ?: 0));
        return $this->view('Admin/Settings/View/index', [
            'pageTitle' => '系统设置',
            'active' => 'settings',
            'settings' => $settings,
            'scraperUrl' => $scraper,
            'status' => $status,
            'health' => [
                'countries' => $countryCount,
                'industries' => $industryCount,
                'keywords' => $keywordCount,
                'database_size' => $dbSize,
                'php' => PHP_VERSION,
                'mysql' => db()->query('SELECT VERSION()')->fetchColumn(),
                'geocities' => (new GeoCities())->count(),
            ],
            'config' => [
                'connect_timeout' => config('scraper.connect_timeout'),
                'read_timeout' => config('scraper.read_timeout'),
                'poll_interval' => config('scraper.poll_interval'),
                'max_concurrent_jobs' => config('scraper.max_concurrent_jobs'),
                'geocoder' => config('geocoder.url'),
            ],
        ]);
    }

    public function testScraper(): string
    {
        $status = (new ScraperClient())->status(true);
        audit('settings.scraper.test', 'scraper', null, ['state' => $status['state'], 'latency' => $status['latency_ms']]);
        if ($status['state'] === 'online') {
            $this->flashSuccess('Scraper 连接成功 · ' . $status['latency_ms'] . ' ms · 发现 ' . $status['job_count'] . ' 个外部任务');
        } else {
            $this->flashError('Scraper 连接异常：' . $status['message']);
        }
        $this->redirect('/settings');
    }

    public function save(): string
    {
        $allowed = [
            'default_language' => ['en', 'zh', 'de', 'fr', 'ja', 'es', 'pt', 'nl', 'it'],
            'default_radius' => null,
            'default_depth' => null,
            'email_finder_enabled' => ['0', '1'],
            'email_domain_blacklist' => null,
            'email_finder_max_pages' => null,
            'data_retention_days' => null,
            'fastmode_default' => ['0', '1'],
        ];
        // Unchecked checkbox values must be persisted as 0, otherwise the
        // previous value would silently remain enabled.
        foreach (['email_finder_enabled', 'fastmode_default'] as $checkboxKey) {
            if (!array_key_exists($checkboxKey, $_POST)) $_POST[$checkboxKey] = '0';
        }
        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO user_settings (user_id, `key`, value, updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)');
        foreach ($allowed as $key => $valid) {
            if (!array_key_exists($key, $_POST)) continue;
            $value = trim((string) $this->input($key));
            if ($valid !== null && !in_array($value, $valid, true)) continue;
            if ($key === 'default_radius') $value = (string) max(100, min(50000, (int) $value));
            if ($key === 'default_depth') $value = (string) max(1, min(50, (int) $value));
            if ($key === 'data_retention_days') $value = (string) max(30, min(3650, (int) $value));
            if ($key === 'email_finder_max_pages') $value = (string) max(1, min(5, (int) $value));
            if ($key === 'email_domain_blacklist') $value = mb_substr(trim($value), 0, 2000);
            $stmt->execute([$this->userId(), $key, $value, now_utc()]);
            if ($key === 'fastmode_default') {
                $global = $pdo->prepare('INSERT INTO app_settings (`key`, value, updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)');
                $global->execute(['fastmode_default', $value, now_utc()]);
            }
        }
        audit('settings.save', 'settings');
        $this->flashSuccess('系统偏好已保存。');
        $this->redirect('/settings');
    }

    public function syncScraperJobs(): string
    {
        $client = new ScraperClient();
        $result = $client->jobs();
        if (!$result['ok']) {
            $this->flashError('无法读取外部任务：' . ($result['error'] ?? '未知错误'));
            $this->redirect('/settings');
        }
        $pdo = db();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $uid = $this->userId();
        $regions = $pdo->query('SELECT r.*, c.iso2, c.name_en AS country_name FROM regions r JOIN countries c ON c.id=r.country_id WHERE r.is_active=1')->fetchAll();
        foreach ($result['jobs'] as $external) {
            $keywords = is_array($external['keywords']) ? implode(', ', $external['keywords']) : (string) ($external['keywords'] ?? '');
            $find = $pdo->prepare('SELECT id FROM scrape_jobs WHERE scraper_job_id = ? AND user_id = ?');
            $find->execute([$external['id'], $uid]);
            $localId = $find->fetchColumn();
            $extStatus = mb_strtolower((string) $external['status']);
            $localStatus = match (true) {
                in_array($extStatus, ['failed', 'error', 'cancelled', 'canceled'], true) => 'failed',
                in_array($extStatus, ['ok', 'done', 'completed', 'success', 'finished'], true) => 'running',
                default => 'running',
            };
            $region = null;
            foreach ($regions as $candidate) {
                if ($keywords !== '' && mb_stripos($keywords, (string) $candidate['name']) !== false) {
                    $region = $candidate;
                    break;
                }
            }
            $matchedKeyword = null;
            $firstKeyword = is_array($external['keywords']) ? (string) ($external['keywords'][0] ?? '') : $keywords;
            if ($firstKeyword !== '') {
                $kw = $pdo->prepare('SELECT * FROM keywords WHERE user_id = ? AND lower(keyword) = lower(?) LIMIT 1');
                $kw->execute([$uid, $firstKeyword]);
                $matchedKeyword = $kw->fetch() ?: null;
            }
            $values = [
                'task_name' => (string) $external['name'],
                'keyword_text' => $keywords ?: '—',
                'language_code' => (string) ($external['lang'] ?: 'en'),
                'latitude' => is_numeric($external['latitude']) ? (float) $external['latitude'] : null,
                'longitude' => is_numeric($external['longitude']) ? (float) $external['longitude'] : null,
                'zoom' => is_numeric($external['zoom']) ? (int) $external['zoom'] : 15,
                'radius' => is_numeric($external['radius']) ? (int) $external['radius'] : 10000,
                'depth' => is_numeric($external['depth']) ? (int) $external['depth'] : 10,
                'fastmode' => $external['fast_mode'] ? 1 : 0,
                'extract_email' => $external['email'] ? 1 : 0,
                'max_time' => (string) ($external['max_time'] ?? '10m'),
                'external_status' => $extStatus,
                'last_synced_at' => now_utc(),
                'industry_id' => $matchedKeyword ? (int) $matchedKeyword['industry_id'] : null,
                'keyword_id' => $matchedKeyword ? (int) $matchedKeyword['id'] : null,
            ];
            if ($localId) {
                if (!$matchedKeyword) {
                    unset($values['industry_id'], $values['keyword_id']);
                }
                $sets = implode(', ', array_map(static fn ($k) => "{$k} = ?", array_keys($values)));
                $stmt = $pdo->prepare("UPDATE scrape_jobs SET {$sets}, updated_at = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([...array_values($values), now_utc(), $localId, $uid]);
                $updated++;
            } else {
                // The scraper is a shared node. Unknown external jobs are not
                // imported automatically, otherwise one account could see
                // another account's scraper results.
                $skipped++;
            }
        }
        audit('settings.scraper.sync', 'scraper', null, ['created' => $created, 'updated' => $updated]);
        $this->flashSuccess("外部任务同步完成：更新 {$updated} 个，跳过 {$skipped} 个未归属本账号的外部任务。");
        $this->redirect('/jobs');
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
