<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class StatsService
{
    private function uid(): int
    {
        return auth_id();
    }

    public function dashboard(): array
    {
        $pdo = db();
        $uid = $this->uid();
        $single = static function (string $sql, array $params = []) use ($pdo): int {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        };

        $stats = [
            'businesses' => $single('SELECT COUNT(*) FROM businesses WHERE user_id = ?', [$uid]),
            'businesses_today' => $single("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND DATE(created_at) = CURDATE()", [$uid]),
            'jobs' => $single('SELECT COUNT(*) FROM scrape_jobs WHERE user_id = ?', [$uid]),
            'jobs_running' => $single("SELECT COUNT(*) FROM scrape_jobs WHERE user_id = ? AND status IN ('running','submitting','submitted','queued')", [$uid]),
            'jobs_completed' => $single("SELECT COUNT(*) FROM scrape_jobs WHERE user_id = ? AND status = 'completed'", [$uid]),
            'jobs_failed' => $single("SELECT COUNT(*) FROM scrape_jobs WHERE user_id = ? AND status IN ('failed','submission_unknown')", [$uid]),
            'with_email' => $single("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND email_status = 'found'", [$uid]),
            'with_website' => $single("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND website IS NOT NULL AND website <> ''", [$uid]),
            'with_phone' => $single("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND phone IS NOT NULL AND phone <> ''", [$uid]),
            'countries_covered' => $single('SELECT COUNT(DISTINCT country_id) FROM businesses WHERE user_id = ? AND country_id IS NOT NULL', [$uid]),
            'active_countries' => $single('SELECT COUNT(*) FROM countries WHERE is_active = 1'),
            'raw_records' => $single('SELECT COUNT(*) FROM raw_records rr JOIN scrape_jobs j ON j.id = rr.job_id WHERE j.user_id = ?', [$uid]),
            'avg_completeness' => 0,
        ];
        $avgStmt = $pdo->prepare('SELECT COALESCE(AVG(completeness_score), 0) FROM businesses WHERE user_id = ?');
        $avgStmt->execute([$uid]);
        $stats['avg_completeness'] = (int) round((float) $avgStmt->fetchColumn());
        $stats['today_delta'] = $stats['businesses_today'];

        $stats['coverage'] = [];
        $coverageStmt = $pdo->prepare("
            SELECT c.id, c.iso2, c.name_en, c.name_local, c.center_lat, c.center_lng,
                   COUNT(b.id) AS business_count
            FROM countries c
            LEFT JOIN businesses b ON b.country_id = c.id AND b.user_id = ?
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY business_count DESC, c.name_en ASC
            LIMIT 20
        ");
        $coverageStmt->execute([$uid]);
        foreach ($coverageStmt->fetchAll() as $row) {
            $row['business_count'] = (int) $row['business_count'];
            $row['name_cn'] = country_name_cn($row['iso2'], $row['name_en']);
            $stats['coverage'][] = $row;
        }

        $recentJobs = $pdo->prepare("
            SELECT j.*, c.iso2, c.name_en AS country_name, r.name AS region_name, i.name AS industry_name
            FROM scrape_jobs j
            LEFT JOIN countries c ON c.id = j.country_id
            LEFT JOIN regions r ON r.id = j.region_id
            LEFT JOIN industries i ON i.id = j.industry_id
            WHERE j.user_id = ?
            ORDER BY j.created_at DESC
            LIMIT 5
        ");
        $recentJobs->execute([$uid]);
        $stats['recent_jobs'] = $recentJobs->fetchAll();

        $recentImports = $pdo->prepare("
            SELECT l.*, j.task_name, j.keyword_text
            FROM import_logs l
            JOIN scrape_jobs j ON j.id = l.job_id
            WHERE j.user_id = ?
            ORDER BY l.created_at DESC
            LIMIT 5
        ");
        $recentImports->execute([$uid]);
        $stats['recent_imports'] = $recentImports->fetchAll();

        $recentBusinesses = $pdo->prepare("
            SELECT b.*, c.iso2, i.name AS industry_name
            FROM businesses b
            LEFT JOIN countries c ON c.id = b.country_id
            LEFT JOIN industries i ON i.id = b.industry_id
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $recentBusinesses->execute([$uid]);
        $stats['recent_businesses'] = $recentBusinesses->fetchAll();

        $stats['volume_series'] = $this->dailySeries(14);
        $stats['quality'] = [
            'missing_phone' => $single("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND (phone IS NULL OR phone = '')", [$uid]),
            'missing_website' => $single("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND (website IS NULL OR website = '')", [$uid]),
            'missing_email' => $single("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND email_status <> 'found'", [$uid]),
            'missing_industry' => $single('SELECT COUNT(*) FROM businesses WHERE user_id = ? AND industry_id IS NULL', [$uid]),
            'missing_coords' => $single('SELECT COUNT(*) FROM businesses WHERE user_id = ? AND (latitude IS NULL OR longitude IS NULL)', [$uid]),
            'duplicate_candidates' => $single("SELECT COUNT(*) FROM (SELECT normalized_phone FROM businesses WHERE user_id = ? AND normalized_phone IS NOT NULL GROUP BY normalized_phone HAVING COUNT(*) > 1) AS dup", [$uid]),
        ];
        return $stats;
    }

    public function dailySeries(int $days = 14): array
    {
        $pdo = db();
        $uid = $this->uid();
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM businesses WHERE user_id = ? AND date(created_at) = ?');
            $stmt->execute([$uid, $date]);
            $out[] = ['date' => $date, 'count' => (int) $stmt->fetchColumn()];
        }
        return $out;
    }

    public function quality(): array
    {
        $pdo = db();
        $uid = $this->uid();
        $one = static function (string $sql, array $params = []) use ($pdo): int {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        };
        return [
            'total' => $one('SELECT COUNT(*) FROM businesses WHERE user_id = ?', [$uid]),
            'missing_phone' => $one("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND (phone IS NULL OR phone = '')", [$uid]),
            'missing_website' => $one("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND (website IS NULL OR website = '')", [$uid]),
            'missing_email' => $one("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND email_status <> 'found'", [$uid]),
            'missing_industry' => $one('SELECT COUNT(*) FROM businesses WHERE user_id = ? AND industry_id IS NULL', [$uid]),
            'missing_address' => $one("SELECT COUNT(*) FROM businesses WHERE user_id = ? AND (address IS NULL OR address = '')", [$uid]),
            'bad_coords' => $one('SELECT COUNT(*) FROM businesses WHERE user_id = ? AND (latitude NOT BETWEEN -90 AND 90 OR longitude NOT BETWEEN -180 AND 180)', [$uid]),
            'duplicate_phone' => $one("SELECT COUNT(*) FROM (SELECT normalized_phone FROM businesses WHERE user_id = ? AND normalized_phone IS NOT NULL GROUP BY normalized_phone HAVING COUNT(*) > 1) AS dup", [$uid]),
            'duplicate_domain' => $one("SELECT COUNT(*) FROM (SELECT website_domain FROM businesses WHERE user_id = ? AND website_domain IS NOT NULL GROUP BY website_domain HAVING COUNT(*) > 1) AS dup", [$uid]),
            'failed_imports' => $one("SELECT COUNT(*) FROM import_logs l JOIN scrape_jobs j ON j.id=l.job_id WHERE j.user_id = ? AND l.status = 'failed'", [$uid]),
        ];
    }
}
