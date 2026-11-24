<?php
declare(strict_types=1);

namespace App\Modules\Admin\Api\Controller;

use App\Core\Controller;
use App\Services\GeoCities;
use App\Services\Geocoder;
use App\Services\ScraperClient;
use App\Services\StatsService;

final class ApiController extends Controller
{
    public function status(): string
    {
        $cached = scraper_status_cache();
        $checked = $cached['checked_at'] ?? null;
        $fresh = false;
        if ($checked) {
            try {
                $fresh = (time() - (new \DateTimeImmutable($checked, new \DateTimeZone('UTC')))->getTimestamp()) < 30;
            } catch (\Throwable) {
                $fresh = false;
            }
        }
        if (!$fresh) {
            $cached = (new ScraperClient())->status(true);
        }
        return $this->ok($cached);
    }

    public function regions(): string
    {
        $countryId = (int) ($_GET['country_id'] ?? 0);
        if ($countryId <= 0) {
            return $this->fail('INVALID_COUNTRY', '缺少 country_id', 422);
        }
        $stmt = db()->prepare('SELECT id, name, name_local, region_type, latitude, longitude, default_zoom FROM regions WHERE country_id = ? AND is_active = 1 ORDER BY name');
        $stmt->execute([$countryId]);
        return $this->ok($stmt->fetchAll());
    }

    public function cities(): string
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $country = strtoupper(trim((string) ($_GET['country'] ?? '')));
        $countryId = (int) ($_GET['country_id'] ?? 0);
        if ($country === '' && $countryId > 0) {
            $stmt = db()->prepare('SELECT iso2 FROM countries WHERE id = ?');
            $stmt->execute([$countryId]);
            $country = strtoupper((string) ($stmt->fetchColumn() ?: ''));
        }
        if (mb_strlen($q) > 100) {
            return $this->fail('QUERY_TOO_LONG', '城市搜索词过长', 422);
        }
        $limit = (int) ($_GET['limit'] ?? 20);
        $limit = max(1, min(5000, $limit));
        $result = (new GeoCities())->search($q, $country, $limit);
        return $this->ok($result['cities'], [
            'available' => $result['available'],
            'total' => $result['total'],
            'country' => $country,
        ]);
    }

    public function regionBoundary(): string
    {
        $regionId = (int) ($_GET['region_id'] ?? 0);
        if ($regionId <= 0) {
            return $this->fail('INVALID_REGION', '缺少 region_id', 422);
        }
        $stmt = db()->prepare('SELECT r.id, r.name, r.latitude, r.longitude, c.name_en AS country_name, c.name_zh AS country_name_zh, c.iso2 FROM regions r JOIN countries c ON c.id = r.country_id WHERE r.id = ?');
        $stmt->execute([$regionId]);
        $region = $stmt->fetch();
        $stmt->closeCursor();
        if (!$region) {
            return $this->fail('REGION_NOT_FOUND', '城市节点不存在', 404);
        }
        $query = trim((string) $region['name'] . ', ' . (string) $region['country_name']);
        $boundary = (new Geocoder())->boundary($query, $region['latitude'] !== null ? (float) $region['latitude'] : null, $region['longitude'] !== null ? (float) $region['longitude'] : null);
        if (!$boundary['ok'] || empty($boundary['available'])) {
            return $this->ok([
                'available' => false,
                'region_id' => $regionId,
                'region_name' => $region['name'],
                'error' => $boundary['error'] ?? '未找到城市行政区边界',
            ]);
        }
        return $this->ok([
            'available' => true,
            'region_id' => $regionId,
            'region_name' => $region['name'],
            'display_name' => $boundary['display_name'],
            'area_km2' => $boundary['area_km2'],
            'radius_km' => $boundary['radius_km'],
            'cached' => $boundary['cached'] ?? false,
        ]);
    }

    public function geocode(): string
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            return $this->fail('QUERY_TOO_SHORT', '请输入至少 2 个字符', 422);
        }
        if (mb_strlen($q) > 180) {
            return $this->fail('QUERY_TOO_LONG', '地址查询过长', 422);
        }
        $result = (new Geocoder())->search($q, 6);
        if (!$result['ok']) {
            return $this->fail('GEOCODER_UNAVAILABLE', $result['error'] ?? '地址搜索失败', 502);
        }
        return $this->ok($result['results'], ['cached' => $result['cached'] ?? false]);
    }

    public function reverse(): string
    {
        $lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false) {
            return $this->fail('INVALID_COORDINATES', '坐标无效', 422);
        }
        $result = (new Geocoder())->reverse((float) $lat, (float) $lng);
        if (!$result['ok']) {
            return $this->fail('GEOCODER_UNAVAILABLE', $result['error'] ?? '逆地理编码失败', 502);
        }
        return $this->ok($result['result'], ['cached' => $result['cached'] ?? false]);
    }

    public function activeJobs(): string
    {
        $stmt = db()->prepare("SELECT id, status, scraper_job_id FROM scrape_jobs WHERE user_id = ? AND status IN ('running', 'submitted') AND scraper_job_id IS NOT NULL ORDER BY updated_at ASC");
        $stmt->execute([$this->userId()]);
        return $this->ok($stmt->fetchAll());
    }

    public function dashboardStats(): string
    {
        return $this->ok((new StatsService())->dashboard());
    }

    public function businesses(): string
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $size = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
        $uid = $this->userId();
        $countStmt = db()->prepare('SELECT COUNT(*) FROM businesses WHERE user_id = ?');
        $countStmt->execute([$uid]);
        $total = (int) $countStmt->fetchColumn();
        $stmt = db()->prepare('SELECT * FROM businesses WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $uid, \PDO::PARAM_INT);
        $stmt->bindValue(2, $size, \PDO::PARAM_INT);
        $stmt->bindValue(3, ($page - 1) * $size, \PDO::PARAM_INT);
        $stmt->execute();
        return $this->ok($stmt->fetchAll(), ['page' => $page, 'page_size' => $size, 'total' => $total]);
    }

    public function jobs(): string
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $size = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
        $uid = $this->userId();
        $countStmt = db()->prepare('SELECT COUNT(*) FROM scrape_jobs WHERE user_id = ?');
        $countStmt->execute([$uid]);
        $total = (int) $countStmt->fetchColumn();
        $stmt = db()->prepare('SELECT * FROM scrape_jobs WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $uid, \PDO::PARAM_INT);
        $stmt->bindValue(2, $size, \PDO::PARAM_INT);
        $stmt->bindValue(3, ($page - 1) * $size, \PDO::PARAM_INT);
        $stmt->execute();
        return $this->ok($stmt->fetchAll(), ['page' => $page, 'page_size' => $size, 'total' => $total]);
    }

    public function scraperJobs(): string
    {
        $result = (new ScraperClient())->jobs();
        if (!$result['ok']) {
            return $this->fail('SCRAPER_UNAVAILABLE', $result['error'] ?? '无法连接采集服务', 502);
        }
        $stmt = db()->prepare('SELECT scraper_job_id FROM scrape_jobs WHERE user_id = ? AND scraper_job_id IS NOT NULL');
        $stmt->execute([$this->userId()]);
        $owned = array_flip(array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
        $jobs = array_values(array_filter($result['jobs'], static fn ($job) => isset($owned[(string) ($job['id'] ?? '')])));
        return $this->ok($jobs, ['scope' => 'current_user', 'count' => count($jobs)]);
    }
}
