<?php
declare(strict_types=1);

namespace App\Modules\Admin\Jobs\Controller;

use App\Core\Controller;
use App\Services\CsvImporter;
use App\Services\GeoCities;
use App\Services\Geocoder;
use App\Services\ScraperClient;

final class JobController extends Controller
{
    public function index(): string
    {
        if ((string) ($_GET['view'] ?? '') === 'flat') {
            return $this->indexFlat();
        }
        return $this->indexBatches();
    }

    private function indexBatches(): string
    {
        $uid = $this->userId();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 12;
        $openBatchId = trim((string) ($_GET['open'] ?? ''));
        if ($openBatchId !== '' && $openBatchId !== 'unbatched' && !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $openBatchId)) {
            $openBatchId = '';
        }

        $countStmt = db()->prepare('SELECT COUNT(*) FROM scrape_batches b WHERE b.user_id = ? AND EXISTS (SELECT 1 FROM scrape_jobs j WHERE j.batch_id = b.id)');
        $countStmt->execute([$uid]);
        $totalBatches = (int) $countStmt->fetchColumn();

        $stmt = db()->prepare("SELECT b.*,
                COUNT(j.id) job_count,
                SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) completed_jobs,
                SUM(CASE WHEN j.status IN ('running','submitting','submitted') THEN 1 ELSE 0 END) active_jobs,
                SUM(CASE WHEN j.status IN ('queued','draft') THEN 1 ELSE 0 END) queued_jobs,
                SUM(CASE WHEN j.status = 'cancelled' THEN 1 ELSE 0 END) cancelled_jobs,
                SUM(CASE WHEN j.status IN ('failed','submission_unknown') THEN 1 ELSE 0 END) failed_jobs
            FROM scrape_batches b
            LEFT JOIN scrape_jobs j ON j.batch_id = b.id AND j.user_id = b.user_id
            WHERE b.user_id = ?
            GROUP BY b.id
            HAVING COUNT(j.id) > 0
            ORDER BY b.created_at DESC");
        $stmt->execute([$uid]);
        $batches = $stmt->fetchAll();

        $jobsStmt = db()->prepare("SELECT j.*, c.iso2, c.name_en AS country_name, r.name AS region_name
            FROM scrape_jobs j
            LEFT JOIN countries c ON c.id = j.country_id
            LEFT JOIN regions r ON r.id = j.region_id
            WHERE j.batch_id = ? AND j.user_id = ?
            ORDER BY j.queue_order ASC, j.created_at ASC
            LIMIT 500");

        $units = [];
        foreach ($batches as $batch) {
            $units[] = ['type' => 'batch', 'batch' => $batch];
            if ($openBatchId === (string) $batch['id']) {
                $jobsStmt->execute([$batch['id'], $uid]);
                foreach ($jobsStmt->fetchAll() as $job) {
                    $units[] = ['type' => 'task', 'job' => $job, 'batch' => $batch];
                }
            }
        }

        $unbatchedCountStmt = db()->prepare('SELECT COUNT(*) FROM scrape_jobs WHERE batch_id IS NULL AND user_id = ?');
        $unbatchedCountStmt->execute([$uid]);
        $unbatchedCount = (int) $unbatchedCountStmt->fetchColumn();
        if ($unbatchedCount > 0) {
            $units[] = ['type' => 'unbatched_header'];
            if ($openBatchId === 'unbatched') {
                $unbatchedStmt = db()->prepare("SELECT j.*, c.iso2, c.name_en AS country_name, r.name AS region_name
                    FROM scrape_jobs j
                    LEFT JOIN countries c ON c.id = j.country_id
                    LEFT JOIN regions r ON r.id = j.region_id
                    WHERE j.batch_id IS NULL AND j.user_id = ?
                    ORDER BY j.created_at DESC
                    LIMIT 500");
                $unbatchedStmt->execute([$uid]);
                foreach ($unbatchedStmt->fetchAll() as $job) {
                    $units[] = ['type' => 'task', 'job' => $job, 'batch' => null];
                }
            }
        }

        $totalUnits = count($units);
        $pages = max(1, (int) ceil($totalUnits / $perPage));
        $page = min(max(1, $page), $pages);
        $pageUnits = array_slice($units, ($page - 1) * $perPage, $perPage);

        return $this->view('Admin/Jobs/View/batches', [
            'pageTitle' => '采集任务',
            'active' => 'jobs',
            'pageUnits' => $pageUnits,
            'openBatchId' => $openBatchId,
            'unbatchedCount' => $unbatchedCount,
            'page' => $page,
            'pages' => $pages,
            'totalBatches' => $totalBatches,
            'totalUnits' => $totalUnits,
        ]);
    }

    private function indexFlat(): string
    {
        $status = trim((string) ($_GET['status'] ?? ''));
        $countryId = (int) ($_GET['country'] ?? 0);
        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 12;

        $uid = $this->userId();
        $where = ['j.user_id = ?'];
        $params = [$uid];
        if ($status !== '') { $where[] = 'j.status = ?'; $params[] = $status; }
        if ($countryId) { $where[] = 'j.country_id = ?'; $params[] = $countryId; }
        if ($q !== '') {
            $where[] = '(j.task_name LIKE ? OR j.keyword_text LIKE ? OR j.scraper_job_id LIKE ?)';
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%");
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $countStmt = db()->prepare("SELECT COUNT(*) FROM scrape_jobs j {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $sql = "SELECT j.*, c.iso2, c.name_en AS country_name, r.name AS region_name,
                       i.name AS industry_name, k.keyword AS keyword_label
                FROM scrape_jobs j
                LEFT JOIN countries c ON c.id = j.country_id
                LEFT JOIN regions r ON r.id = j.region_id
                LEFT JOIN industries i ON i.id = j.industry_id
                LEFT JOIN keywords k ON k.id = j.keyword_id
                {$whereSql}
                ORDER BY CASE j.status
                    WHEN 'completed' THEN 0
                    WHEN 'partial' THEN 1
                    WHEN 'empty' THEN 2
                    WHEN 'running' THEN 3
                    WHEN 'submitted' THEN 4
                    WHEN 'submitting' THEN 5
                    WHEN 'queued' THEN 6
                    WHEN 'draft' THEN 7
                    WHEN 'failed' THEN 8
                    WHEN 'submission_unknown' THEN 9
                    WHEN 'cancelled' THEN 10
                    ELSE 11
                END ASC, j.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = db()->prepare($sql);
        $i = 1;
        foreach ($params as $p) { $stmt->bindValue($i++, $p, \PDO::PARAM_STR); }
        $stmt->bindValue($i++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($i, ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();
        $jobs = $stmt->fetchAll();

        $countsStmt = db()->prepare("SELECT
            COUNT(*) total,
            SUM(CASE WHEN status IN ('running','submitting','submitted') THEN 1 ELSE 0 END) active,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) completed,
            SUM(CASE WHEN status IN ('failed','submission_unknown') THEN 1 ELSE 0 END) failed,
            COALESCE(SUM(result_count), 0) imported_rows
            FROM scrape_jobs WHERE user_id = ?");
        $countsStmt->execute([$uid]);
        $counts = $countsStmt->fetch();

        $countries = db()->query('SELECT id, iso2, name_en FROM countries WHERE is_active = 1 ORDER BY name_en')->fetchAll();
        $cancelledStmt = db()->prepare("SELECT COUNT(*) FROM scrape_jobs WHERE user_id = ? AND status = 'cancelled'");
        $cancelledStmt->execute([$uid]);
        $cancelledCount = (int) $cancelledStmt->fetchColumn();
        $emptyStmt = db()->prepare("SELECT COUNT(*) FROM scrape_jobs j WHERE j.user_id = ? AND j.status IN ('completed', 'empty') AND j.result_count = 0 AND NOT EXISTS (SELECT 1 FROM raw_records rr WHERE rr.job_id = j.id)");
        $emptyStmt->execute([$uid]);
        $emptyCount = (int) $emptyStmt->fetchColumn();

        $batchStmt = db()->prepare('SELECT * FROM scrape_batches b WHERE b.user_id = ? AND b.queue_mode = 1 AND EXISTS (SELECT 1 FROM scrape_jobs j WHERE j.batch_id = b.id) ORDER BY b.created_at DESC LIMIT 1');
        $batchStmt->execute([$uid]);
        $queueBatch = $batchStmt->fetch() ?: null;
        if ($queueBatch) {
            $statusStmt = db()->prepare('SELECT status, COUNT(*) n FROM scrape_jobs WHERE batch_id = ? AND user_id = ? GROUP BY status');
            $statusStmt->execute([$queueBatch['id'], $uid]);
            $queueBatch['status_counts'] = [];
            foreach ($statusStmt->fetchAll() as $row) {
                $queueBatch['status_counts'][$row['status']] = (int) $row['n'];
            }
            $sampleStmt = db()->prepare('SELECT id FROM scrape_jobs WHERE batch_id = ? AND user_id = ? ORDER BY queue_order ASC LIMIT 1');
            $sampleStmt->execute([$queueBatch['id'], $uid]);
            $queueBatch['sample_job_id'] = (string) ($sampleStmt->fetchColumn() ?: '');
        }
        return $this->view('Admin/Jobs/View/index', [
            'pageTitle' => '采集任务',
            'active' => 'jobs',
            'jobs' => $jobs,
            'countries' => $countries,
            'filters' => compact('status', 'countryId', 'q', 'page'),
            'page' => $page,
            'total' => $total,
            'pages' => $pages,
            'perPage' => $perPage,
            'counts' => $counts,
            'cancelledCount' => $cancelledCount,
            'emptyCount' => $emptyCount,
            'queueBatch' => $queueBatch,
        ]);
    }

    public function create(): string
    {
        $countries = db()->query('SELECT * FROM countries WHERE is_active = 1 ORDER BY name_en')->fetchAll();
        $regions = db()->query('SELECT r.*, c.iso2 FROM regions r JOIN countries c ON c.id = r.country_id WHERE r.is_active = 1 ORDER BY c.name_en, r.name')->fetchAll();
        $industries = db()->query('SELECT * FROM industries WHERE is_active = 1 ORDER BY name')->fetchAll();
        $keywords = db()->prepare('SELECT k.*, c.iso2 FROM keywords k LEFT JOIN countries c ON c.id = k.country_id WHERE k.user_id = ? AND k.is_active = 1 ORDER BY k.keyword');
        $keywords->execute([$this->userId()]);
        $keywords = $keywords->fetchAll();

        $defaultCountry = null;
        $defaultRegion = null;
        foreach ($countries as $candidate) {
            if ($candidate['iso2'] === 'DE') {
                $defaultCountry = $candidate;
                break;
            }
        }
        if ($defaultCountry) {
            foreach ($regions as $candidate) {
                if ((int) $candidate['country_id'] === (int) $defaultCountry['id'] && $candidate['name'] === 'Berlin') {
                    $defaultRegion = $candidate;
                    break;
                }
            }
        }
        $countryParam = isset($_GET['country']) ? (int) $_GET['country'] : null;
        $regionParam = isset($_GET['region']) ? (int) $_GET['region'] : null;
        $selectedKeyword = (int) ($_GET['keyword'] ?? 0);
        $selectedCountry = $countryParam ?? (int) ($defaultCountry['id'] ?? 0);
        $selectedRegion = $regionParam ?? (($countryParam === null && !$selectedKeyword) ? (int) ($defaultRegion['id'] ?? 0) : 0);
        $defaults = [
            'task_name' => 'B2B 采购商 — ' . gmdate('m月d日'),
            'keyword_text' => '',
            'language_code' => 'en',
            'latitude' => (float) ($defaultRegion['latitude'] ?? 52.5200),
            'longitude' => (float) ($defaultRegion['longitude'] ?? 13.4050),
            'zoom' => (int) ($defaultRegion['default_zoom'] ?? 12),
            'radius' => 10000,
            'depth' => 10,
            'max_time' => '10m',
            'fastmode' => (int) app_setting('fastmode_default', '0'),
            'extract_email' => 1,
            'keyword_id' => $selectedKeyword ?: null,
            'country_id' => $defaultCountry['id'] ?? null,
            'region_id' => $defaultRegion['id'] ?? null,
        ];
        if (!$selectedKeyword) {
            // No implicit keyword: the operator either types one or picks a
            // preset from the keyword library. This avoids implying that a
            // particular industry/market is preselected.
            $defaults['keyword_text'] = '';
            $defaults['keyword_id'] = null;
            $defaults['industry_id'] = null;
            $defaults['language_code'] = 'en';
        }
        foreach ($keywords as $k) {
            if ((int) $k['id'] === $selectedKeyword) {
                $defaults['keyword_text'] = $k['keyword'];
                $defaults['language_code'] = $k['language_code'];
                $defaults['industry_id'] = (int) $k['industry_id'];
                $defaults['country_id'] = (int) $k['country_id'];
                $defaults['task_name'] = mb_substr((string) $k['keyword'], 0, 60) . ' — ' . gmdate('m月d日');
                $selectedCountry = (int) $k['country_id'];
            }
        }
        // If a keyword was selected, infer the matching city node from the keyword
        // text instead of leaving the task at an arbitrary country/world center.
        if ($selectedKeyword && !$selectedRegion) {
            $keywordSnapshot = (string) ($defaults['keyword_text'] ?? '');
            $bestRegion = null;
            $bestLength = 0;
            foreach ($regions as $candidate) {
                if ((int) $candidate['country_id'] !== $selectedCountry) continue;
                foreach ([$candidate['name'], $candidate['name_local']] as $regionLabel) {
                    $regionLabel = trim((string) $regionLabel);
                    if ($regionLabel === '') continue;
                    if (mb_stripos($keywordSnapshot, $regionLabel) !== false && mb_strlen($regionLabel) > $bestLength) {
                        $bestRegion = $candidate;
                        $bestLength = mb_strlen($regionLabel);
                    }
                }
            }
            if ($bestRegion) {
                $selectedRegion = (int) $bestRegion['id'];
                $defaults['latitude'] = (float) $bestRegion['latitude'];
                $defaults['longitude'] = (float) $bestRegion['longitude'];
                $defaults['zoom'] = (int) ($bestRegion['default_zoom'] ?: 12);
            }
        }
        foreach ($regions as $r) {
            if ((int) $r['id'] === $selectedRegion) {
                $defaults['latitude'] = (float) $r['latitude'];
                $defaults['longitude'] = (float) $r['longitude'];
                $defaults['zoom'] = (int) ($r['default_zoom'] ?: 12);
                $selectedCountry = (int) $r['country_id'];
            }
        }
        if ($selectedCountry) {
            $defaults['country_id'] = $selectedCountry;
        }
        if ($selectedRegion) {
            $defaults['region_id'] = $selectedRegion;
        }
        $selectedCountryData = null;
        foreach ($countries as $c) {
            if ((int) $c['id'] === $selectedCountry) {
                $selectedCountryData = $c;
                if (!$selectedRegion && !$selectedKeyword && $c['center_lat'] !== null) {
                    $defaults['latitude'] = (float) $c['center_lat'];
                    $defaults['longitude'] = (float) $c['center_lng'];
                    $defaults['zoom'] = (int) $c['default_zoom'];
                }
            }
        }

        $recentJobsStmt = db()->prepare('SELECT id, task_name, status, created_at FROM scrape_jobs WHERE user_id = ? ORDER BY created_at DESC LIMIT 4');
        $recentJobsStmt->execute([$this->userId()]);
        $recentJobs = $recentJobsStmt->fetchAll();
        return $this->view('Admin/Jobs/View/create', [
            'pageTitle' => '全球选点',
            'active' => 'targeting',
            'countries' => $countries,
            'regions' => $regions,
            'industries' => $industries,
            'keywords' => $keywords,
            'defaults' => $defaults,
            'selectedCountryData' => $selectedCountryData,
            'mapConfig' => config('map'),
            'regionsJson' => json_encode(array_map(static fn ($r) => $r + ['name_cn' => place_name_cn($r['name'])], $regions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'countriesJson' => json_encode($countries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'recentJobs' => $recentJobs,
            'geoCitiesCount' => (new GeoCities())->count(),
            'useMap' => true,
        ]);
    }

    public function store(): string
    {
        $keywordText = trim((string) $this->input('keyword_text'));
        $taskName = trim((string) $this->input('task_name'));
        $lat = $this->float('latitude', 999);
        $lng = $this->float('longitude', 999);
        if ($keywordText === '' || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $this->flashError('关键词与有效经纬度是创建任务的必要条件。');
            $this->redirect('/jobs/create');
        }
        if ($taskName === '') $taskName = mb_substr($keywordText, 0, 80) . ' · ' . gmdate('m-d H:i');

        $countryId = $this->integer('country_id') ?: null;
        $regionId = $this->resolveRegionSelection();
        $industryId = $this->integer('industry_id') ?: null;
        $keywordId = $this->integer('keyword_id') ?: null;
        if ($keywordId) {
            $keywordCheck = db()->prepare('SELECT id FROM keywords WHERE id = ? AND user_id = ?');
            $keywordCheck->execute([$keywordId, $this->userId()]);
            if (!$keywordCheck->fetchColumn()) $keywordId = null;
        }
        $language = preg_replace('/[^a-zA-Z-]/', '', (string) $this->input('language_code', 'en')) ?: 'en';
        $zoom = max(1, min(20, $this->integer('zoom', 15)));
        $coverageGrid = $this->boolean('coverage_grid');
        // A single scan is capped at 10 km. Coverage mode may use a larger
        // administrative-boundary radius and is handled separately below.
        $radius = max(100, min($coverageGrid ? 100000 : 10000, $this->integer('radius', 10000)));
        $depth = max(1, min(50, $this->integer('depth', 10)));
        $maxTime = preg_replace('/[^0-9a-zA-Z.]/', '', (string) $this->input('max_time', '10m')) ?: '10m';
        $fastmode = (int) app_setting('fastmode_default', '0') === 1;
        $email = $this->boolean('extract_email');
        $submitNow = $this->boolean('submit_now');
        $targetAddress = trim((string) $this->input('target_address'));
        $gridCellKm = max(1.0, min(10.0, $this->float('grid_cell_km', 5)));
        $gridCellMeters = (int) round($gridCellKm * 1000);

        if ($coverageGrid) {
            // A very large batch is still bounded to keep DB inserts and the
            // local queue practical.
            // Prefer the administrative boundary so different-sized cities get
            // different coverage radii. Fall back to the operator's radius.
            if ($regionId) {
                $regionStmt = db()->prepare('SELECT r.name, c.name_en AS country_name FROM regions r JOIN countries c ON c.id = r.country_id WHERE r.id = ?');
                $regionStmt->execute([$regionId]);
                $regionRow = $regionStmt->fetch();
                if ($regionRow) {
                    $boundary = (new Geocoder())->boundary(trim((string) $regionRow['name'] . ', ' . (string) $regionRow['country_name']), $lat, $lng);
                    if (!empty($boundary['available']) && !empty($boundary['radius_km'])) {
                        $radius = (int) round(min(100, max(1, (float) $boundary['radius_km'])) * 1000);
                    }
                }
            }
            $points = $this->coverageGridPoints($lat, $lng, $radius, $gridCellMeters);
            if (!$points) {
                $this->flashError('覆盖网格为空，请检查扫描半径和单格半径。');
                $this->redirect('/jobs/create');
            }
            if (count($points) > 200) {
                $this->flashError('覆盖范围需要 ' . count($points) . ' 个子任务，超过 200 个上限；请增大单格半径或缩小扫描范围。');
                $this->redirect('/jobs/create');
            }
            $batchId = $this->uuid();
            $batchStatus = $submitNow ? 'queued' : 'draft';
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $insBatch = $pdo->prepare('INSERT INTO scrape_batches (id, user_id, name, status, queue_mode, total_jobs, notes, created_at, updated_at) VALUES (?,?,?,?,1,?,?,?,?)');
                $insBatch->execute([
                    $batchId, $this->userId(), mb_substr($taskName, 0, 160) . ' · 全市覆盖', $batchStatus,
                    count($points), sprintf('全市覆盖：行政区覆盖半径 %.1f km，单格半径 %.1f km，%d 个子任务', $radius / 1000, $gridCellKm, count($points)),
                    now_utc(), now_utc(),
                ]);
                $insJob = $pdo->prepare('INSERT INTO scrape_jobs (id, user_id, batch_id, country_id, region_id, industry_id, keyword_id, task_name, keyword_text, language_code, latitude, longitude, zoom, radius, depth, fastmode, extract_email, queue_order, max_time, status, target_address, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                foreach ($points as $index => $point) {
                    $gridLabel = '网格 ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '/' . count($points);
                    $insJob->execute([
                        $this->uuid(), $this->userId(), $batchId, $countryId, $regionId, $industryId, $keywordId,
                        mb_substr($taskName, 0, 170) . ' · ' . $gridLabel, $keywordText, $language,
                        $point['lat'], $point['lng'], $zoom, $gridCellMeters, $depth,
                        $fastmode ? 1 : 0, $email ? 1 : 0, $index + 1, $maxTime, $batchStatus,
                        trim(($targetAddress ?: $taskName) . ' · ' . $gridLabel),
                        now_utc(), now_utc(),
                    ]);
                }
                audit('batch.create.coverage', 'scrape_batch', $batchId, [
                    'jobs' => count($points), 'coverage_km' => $radius / 1000, 'cell_km' => $gridCellKm,
                ]);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $this->flashError('全市覆盖任务创建失败：' . $e->getMessage());
                $this->redirect('/jobs/create');
            }
            $statusLabel = $submitNow ? '等待提交' : '草稿';
            $this->flashSuccess('全市覆盖任务已生成：' . count($points) . ' 个网格子任务（' . $statusLabel . '）。先手动提交第一个，之后完成一个会自动提交下一个；请保持当前任务页打开。');
            $this->redirect('/jobs?status=' . $batchStatus);
        }

        $id = $this->uuid();
        $now = now_utc();
        $stmt = db()->prepare('INSERT INTO scrape_jobs (id, user_id, country_id, region_id, industry_id, keyword_id, task_name, keyword_text, language_code, latitude, longitude, zoom, radius, depth, fastmode, extract_email, max_time, status, target_address, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $id, $this->userId(), $countryId, $regionId, $industryId, $keywordId, $taskName, $keywordText, $language,
            $lat, $lng, $zoom, $radius, $depth, $fastmode ? 1 : 0, $email ? 1 : 0, $maxTime,
            $submitNow ? 'queued' : 'draft', $targetAddress ?: null, $now, $now,
        ]);
        audit('job.create', 'scrape_job', $id, ['keyword' => $keywordText, 'lat' => $lat, 'lng' => $lng]);

        if ($submitNow) {
            $this->submitJob($id);
            $this->redirect('/jobs/' . $id);
        }

        $this->flashSuccess('选点任务已保存为草稿，可复核后再提交。');
        $this->redirect('/jobs/' . $id);
    }

    public function show(array $params): string
    {
        $job = $this->findJob($params['id']);
        if (!$job) {
            http_response_code(404);
            return $this->view('Common/View/error', ['pageTitle' => '任务不存在', 'code' => '404', 'message' => '该采集任务不存在。']);
        }
        $raws = db()->prepare('SELECT id, row_no, external_data_id, content_hash, imported_at FROM raw_records WHERE job_id = ? ORDER BY row_no LIMIT 8');
        $raws->execute([$job['id']]);
        $rawRecords = $raws->fetchAll();
        $rawCountStmt = db()->prepare('SELECT COUNT(*) FROM raw_records WHERE job_id = ?');
        $rawCountStmt->execute([$job['id']]);
        $rawCount = (int) $rawCountStmt->fetchColumn();
        $logsStmt = db()->prepare('SELECT * FROM import_logs WHERE job_id = ? ORDER BY created_at DESC LIMIT 10');
        $logsStmt->execute([$job['id']]);
        $logs = $logsStmt->fetchAll();
        $sourcesStmt = db()->prepare('SELECT COUNT(DISTINCT business_id) FROM business_sources WHERE job_id = ?');
        $sourcesStmt->execute([$job['id']]);
        $businessCount = (int) $sourcesStmt->fetchColumn();
        $batchCancelledCount = 0;
        if (!empty($job['batch_id'])) {
            $bc = db()->prepare("SELECT COUNT(*) FROM scrape_jobs WHERE batch_id = ? AND user_id = ? AND status = 'cancelled'");
            $bc->execute([$job['batch_id'], $this->userId()]);
            $batchCancelledCount = (int) $bc->fetchColumn();
        }

        return $this->view('Admin/Jobs/View/show', [
            'pageTitle' => $job['task_name'],
            'active' => 'jobs',
            'job' => $job,
            'rawRecords' => $rawRecords,
            'rawCount' => $rawCount,
            'logs' => $logs,
            'businessCount' => $businessCount,
            'batchCancelledCount' => $batchCancelledCount,
            'mapConfig' => config('map'),
            'useMap' => true,
        ]);
    }

    public function sync(array $params): string
    {
        $job = $this->findJob($params['id']);
        if (!$job) { $this->flashError('任务不存在。'); $this->redirect('/jobs'); }
        if (empty($job['scraper_job_id'])) {
            $this->flashError('该任务尚未获得外部 Scraper ID，无法同步。');
            $this->redirect('/jobs/' . $job['id']);
        }
        $client = new ScraperClient();
        $result = $client->jobs();
        if (!$result['ok']) {
            $this->flashError('无法读取 Scraper 任务列表：' . ($result['error'] ?? '未知错误'));
            $this->redirect('/jobs/' . $job['id']);
        }
        $external = null;
        foreach ($result['jobs'] as $item) {
            if ($item['id'] === $job['scraper_job_id']) { $external = $item; break; }
        }
        if (!$external) {
            $this->flashError('外部任务列表中未找到该 ID，未更改本地状态。');
            $this->redirect('/jobs/' . $job['id']);
        }
        $externalStatus = mb_strtolower((string) $external['status']);
        $updates = ['external_status = ?', 'last_synced_at = ?', 'updated_at = ?'];
        $values = [$externalStatus, now_utc(), now_utc()];
        $completedStatuses = ['ok', 'done', 'completed', 'success', 'finished'];
        $failedStatuses = ['failed', 'error', 'cancelled', 'canceled'];

        if (in_array($externalStatus, $completedStatuses, true)) {
            $download = $client->downloadCsv($job['scraper_job_id']);
            if ($download['ok']) {
                $tmp = tempnam(sys_get_temp_dir(), 'atlas-csv-');
                file_put_contents($tmp, $download['body']);
                $import = (new CsvImporter())->importPath($job['id'], $tmp, $job['scraper_job_id'] . '.csv');
                @unlink($tmp);
                if ($import['ok']) {
                    $updates[] = 'status = ?'; $values[] = $import['status'];
                    $updates[] = 'error_message = ?'; $values[] = null;
                    $updates[] = 'started_at = COALESCE(started_at, ?)'; $values[] = $nowTmp = now_utc();
                    $externalNote = '';
                    $deleteResult = $client->cancel($job['scraper_job_id']);
                    if ($deleteResult['ok']) {
                        $values[0] = 'deleted_after_import';
                        $externalNote = '，外部任务已删除';
                    } else {
                        $values[0] = 'delete_after_import_failed';
                        $externalNote = '；外部任务删除失败：' . ($deleteResult['error'] ?? '未知错误');
                    }
                    audit('job.external_delete_after_import', 'scrape_job', $job['id'], ['external_deleted' => $deleteResult['ok'], 'error' => $deleteResult['error'] ?? null]);
                    $this->flashSuccess("同步完成：外部状态 {$externalStatus}，导入 {$import['rows_imported']} 行，新建 {$import['businesses_created']} 家商家{$externalNote}。");
                } else {
                    $updates[] = 'status = ?'; $values[] = 'failed';
                    $updates[] = 'error_message = ?'; $values[] = $import['error'] ?? 'CSV 导入失败';
                    $this->flashError('CSV 已下载但导入失败：' . ($import['error'] ?? '未知错误'));
                }
            } elseif (!empty($download['empty'])) {
                $rawCountStmt = db()->prepare('SELECT COUNT(*) FROM raw_records WHERE job_id = ?');
                $rawCountStmt->execute([$job['id']]);
                $existingRaws = (int) $rawCountStmt->fetchColumn();
                if ($existingRaws > 0) {
                    $this->flashWarning('外部任务返回空 CSV，但本地已有 ' . $existingRaws . ' 条原始记录，未降级已有数据。');
                } else {
                    $updates[] = 'status = ?'; $values[] = 'completed';
                    $updates[] = 'result_count = 0';
                    $updates[] = 'imported_at = ?'; $values[] = now_utc();
                    $updates[] = 'completed_at = ?'; $values[] = now_utc();
                    $updates[] = 'error_message = ?'; $values[] = null;
                    $log = db()->prepare('INSERT INTO import_logs (job_id, status, message, rows_total, rows_imported, rows_skipped, created_at) VALUES (?,?,?,?,?,?,?)');
                    $log->execute([$job['id'], 'empty', '外部任务已完成，CSV 返回 0 字节：目标区域可能没有结果，或坐标与关键词不匹配。', 0, 0, 0, now_utc()]);
                    $this->flashWarning('外部任务已完成，但 CSV 为 0 字节，系统已标记为空结果。请检查目标坐标和缩放级别是否与关键词匹配，再重新创建任务。');
                }
            } else {
                $updates[] = 'status = ?'; $values[] = 'failed';
                $updates[] = 'error_message = ?'; $values[] = '外部已完成但 CSV 下载失败：' . ($download['error'] ?? '');
                $this->flashError('外部已完成，但 CSV 下载失败。可稍后重试同步。');
            }
        } elseif (in_array($externalStatus, $failedStatuses, true)) {
            $updates[] = 'status = ?'; $values[] = 'failed';
            $updates[] = 'error_message = ?'; $values[] = 'Scraper 外部状态：' . $externalStatus;
        } else {
            $updates[] = 'status = ?'; $values[] = 'running';
            $updates[] = 'started_at = COALESCE(started_at, ?)'; $values[] = now_utc();
        }
        $values[] = $job['id'];
        $values[] = $this->userId();
        $stmt = db()->prepare('UPDATE scrape_jobs SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?');
        $stmt->execute($values);
        audit('job.sync', 'scrape_job', $job['id'], ['external_status' => $externalStatus]);

        // Coverage batches are sequential: after a task finishes, claim and
        // submit the next queued grid task if the queue is not paused.
        $nextJobId = null;
        $statusStmt = db()->prepare('SELECT status FROM scrape_jobs WHERE id = ? AND user_id = ?');
        $statusStmt->execute([$job['id'], $this->userId()]);
        $finalStatus = (string) ($statusStmt->fetchColumn() ?: '');
        if (!empty($job['batch_id']) && in_array($finalStatus, ['completed', 'partial', 'failed'], true)) {
            $nextJobId = $this->advanceCoverageQueue($job['batch_id']);
            if ($nextJobId) {
                $this->flashSuccess('队列继续：已自动提交下一个任务。保持当前任务页打开可继续自动推进。');
            }
        }
        $this->redirect($nextJobId ? '/jobs/' . $nextJobId : '/jobs/' . $job['id']);
    }

    public function destroy(array $params): string
    {
        $job = $this->findJob($params['id']);
        if (!$job) {
            $this->flashError('任务不存在。');
            $this->redirect('/jobs');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $result = $this->deleteJobRow($job, $pdo);
            audit('job.delete', 'scrape_job', $job['id'], $result + ['status' => $job['status']]);
            $pdo->commit();
            $this->cleanupEmptyBatches($this->userId());
            $extra = '';
            if ($result['raw_records'] > 0 || $result['deleted_businesses'] > 0) {
                $extra = "，并清理 {$result['raw_records']} 条原始记录、{$result['deleted_businesses']} 家仅属于该任务的商家";
            }
            $this->flashSuccess('任务已删除' . $extra . '；外部 Scraper 任务不会被删除。');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->flashError('删除失败：' . $e->getMessage());
            $this->redirect('/jobs/' . $job['id']);
        }
        $this->redirect('/jobs');
    }

    public function deleteCancelled(): string
    {
        return $this->bulkDeleteCancelled($this->userId(), null);
    }

    public function deleteCancelledBatch(array $params): string
    {
        $job = $this->findJob($params['id']);
        if (!$job || empty($job['batch_id'])) {
            $this->flashError('该任务不属于批次。');
            $this->redirect($job ? '/jobs/' . $job['id'] : '/jobs');
        }
        return $this->bulkDeleteCancelled($this->userId(), (string) $job['batch_id']);
    }

    public function deleteUnbatched(): string
    {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, batch_id, status, result_count, raw_csv_path, scraper_job_id, task_name FROM scrape_jobs WHERE user_id = ? AND batch_id IS NULL');
        $stmt->execute([$this->userId()]);
        $jobs = $stmt->fetchAll();
        if (!$jobs) {
            $this->flashSuccess('当前没有未分组任务。');
            $this->redirect('/jobs');
        }
        $pdo->beginTransaction();
        try {
            $deleted = 0;
            $raw = 0;
            $businesses = 0;
            foreach ($jobs as $job) {
                $result = $this->deleteJobRow($job, $pdo);
                $deleted++;
                $raw += $result['raw_records'];
                $businesses += $result['deleted_businesses'];
            }
            audit('job.delete_unbatched', 'scrape_job', 'unbatched', ['tasks' => $deleted, 'raw_records' => $raw, 'businesses' => $businesses]);
            $pdo->commit();
            $this->cleanupEmptyBatches($this->userId());
            $this->flashSuccess("已删除 {$deleted} 个未分组任务，并清理 {$raw} 条原始记录、{$businesses} 家仅属于这些任务的商家。");
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->flashError('删除未分组任务失败：' . $e->getMessage());
            $this->redirect('/jobs');
        }
        $this->redirect('/jobs');
    }

    public function deleteEmpty(): string
    {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, batch_id, status, result_count, raw_csv_path, scraper_job_id, task_name FROM scrape_jobs j WHERE j.user_id = ? AND j.status IN ('completed', 'empty') AND j.result_count = 0 AND NOT EXISTS (SELECT 1 FROM raw_records rr WHERE rr.job_id = j.id) ORDER BY j.created_at DESC");
        $stmt->execute([$this->userId()]);
        $jobs = $stmt->fetchAll();
        if (!$jobs) {
            $this->flashSuccess('当前没有可删除的空结果任务。');
            $this->redirect('/jobs');
        }
        $pdo->beginTransaction();
        try {
            $deleted = 0;
            $raw = 0;
            $businesses = 0;
            foreach ($jobs as $job) {
                $result = $this->deleteJobRow($job, $pdo);
                $deleted++;
                $raw += $result['raw_records'];
                $businesses += $result['deleted_businesses'];
            }
            audit('job.delete_empty', 'scrape_job', 'all', ['tasks' => $deleted, 'raw_records' => $raw, 'businesses' => $businesses]);
            $pdo->commit();
            $this->cleanupEmptyBatches($this->userId());
            $this->flashSuccess("已删除 {$deleted} 个空结果任务，并清理 {$raw} 条原始记录、{$businesses} 家仅属于这些任务的商家。");
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->flashError('批量删除空结果失败：' . $e->getMessage());
            $this->redirect('/jobs');
        }
        $this->redirect('/jobs');
    }

    private function bulkDeleteCancelled(int $userId, ?string $batchId): string
    {
        $pdo = db();
        $sql = "SELECT id, batch_id, status, result_count, raw_csv_path, scraper_job_id, task_name FROM scrape_jobs WHERE user_id = ? AND status = 'cancelled'";
        $params = [$userId];
        if ($batchId !== null) {
            $sql .= ' AND batch_id = ?';
            $params[] = $batchId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll();
        if (!$jobs) {
            $this->flashSuccess('当前没有可删除的已取消任务。');
            $this->redirect('/jobs?status=cancelled');
        }
        $pdo->beginTransaction();
        try {
            $deleted = 0;
            $raw = 0;
            $businesses = 0;
            foreach ($jobs as $job) {
                $result = $this->deleteJobRow($job, $pdo);
                $deleted++;
                $raw += $result['raw_records'];
                $businesses += $result['deleted_businesses'];
            }
            audit('job.delete_cancelled', 'scrape_job', $batchId ?: 'all', ['tasks' => $deleted, 'raw_records' => $raw, 'businesses' => $businesses, 'batch_id' => $batchId]);
            $pdo->commit();
            $this->cleanupEmptyBatches($userId);
            $this->flashSuccess("已删除 {$deleted} 个已取消任务，并清理 {$raw} 条原始记录、{$businesses} 家仅属于这些任务的商家。");
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->flashError('批量删除失败：' . $e->getMessage());
            $this->redirect('/jobs?status=cancelled');
        }
        $this->redirect('/jobs?status=cancelled');
    }

    private function cancelExternalAfterImport(array $job): array
    {
        $result = (new ScraperClient())->cancel((string) ($job['scraper_job_id'] ?? ''));
        $status = $result['ok'] ? 'deleted_after_import' : 'delete_after_import_failed';
        db()->prepare('UPDATE scrape_jobs SET external_status = ?, updated_at = ? WHERE id = ? AND user_id = ?')
            ->execute([$status, now_utc(), $job['id'], $this->userId()]);
        audit('job.external_delete_after_import', 'scrape_job', $job['id'], ['external_deleted' => $result['ok'], 'error' => $result['error'] ?? null]);
        return $result;
    }

    private function deleteJobRow(array $job, \PDO $pdo): array
    {
        $rawStmt = $pdo->prepare('SELECT COUNT(*) FROM raw_records WHERE job_id = ?');
        $rawStmt->execute([$job['id']]);
        $rawCount = (int) $rawStmt->fetchColumn();
        $businessStmt = $pdo->prepare('SELECT DISTINCT business_id FROM business_sources WHERE job_id = ?');
        $businessStmt->execute([$job['id']]);
        $businessIds = array_map('intval', $businessStmt->fetchAll(\PDO::FETCH_COLUMN));

        if (!empty($job['raw_csv_path'])) {
            $relative = ltrim((string) $job['raw_csv_path'], '/');
            $real = realpath(base_path($relative));
            $allowed = realpath(base_path('storage/raw'));
            if ($real && $allowed && str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) && is_file($real)) {
                @unlink($real);
            }
        }
        if (!empty($job['batch_id'])) {
            $pdo->prepare('UPDATE scrape_batches SET total_jobs = GREATEST(total_jobs - 1, 0), updated_at = ? WHERE id = ? AND user_id = ?')
                ->execute([now_utc(), $job['batch_id'], $this->userId()]);
        }
        $pdo->prepare('DELETE FROM scrape_jobs WHERE id = ? AND user_id = ?')->execute([$job['id'], $this->userId()]);

        $deletedBusinesses = 0;
        if ($businessIds) {
            $placeholders = implode(',', array_fill(0, count($businessIds), '?'));
            $deleteBusinesses = $pdo->prepare("DELETE FROM businesses
                WHERE user_id = ? AND id IN ({$placeholders})
                  AND NOT EXISTS (SELECT 1 FROM business_sources bs WHERE bs.business_id = businesses.id)");
            $deleteBusinesses->execute([$this->userId(), ...$businessIds]);
            $deletedBusinesses = $deleteBusinesses->rowCount();
        }
        return [
            'raw_records' => $rawCount,
            'linked_businesses' => count($businessIds),
            'deleted_businesses' => $deletedBusinesses,
            'scraper_job_id' => $job['scraper_job_id'],
            'task_name' => $job['task_name'],
        ];
    }

    public function retry(array $params): string
    {
        $job = $this->findJob($params['id']);
        if (!$job) { $this->flashError('任务不存在。'); $this->redirect('/jobs'); }
        if ($job['status'] === 'submission_unknown') {
            $this->flashError('提交结果未知的任务禁止直接重试，以免在 Scraper 中创建重复任务。请先人工核对外部任务列表。');
            $this->redirect('/jobs/' . $job['id']);
        }
        if (!in_array($job['status'], ['draft', 'queued', 'failed'], true)) {
            $this->flashError('只有草稿、排队或失败任务可以提交。');
            $this->redirect('/jobs/' . $job['id']);
        }
        $this->submitJob($job['id']);
        $this->redirect('/jobs/' . $job['id']);
    }

    public function importCsv(array $params): string
    {
        $job = $this->findJob($params['id']);
        if (!$job) { $this->flashError('任务不存在。'); $this->redirect('/jobs'); }
        if (empty($job['scraper_job_id'])) {
            $this->flashError('任务没有外部 Scraper ID，无法下载 CSV。请手动上传。');
            $this->redirect('/jobs/' . $job['id']);
        }
        $download = (new ScraperClient())->downloadCsv($job['scraper_job_id']);
        if (!$download['ok']) {
            if (!empty($download['empty'])) {
                $this->flashWarning('Scraper 返回 0 字节 CSV：该区域可能没有搜索结果，或结果文件仍在生成。');
            } else {
                $this->flashError('CSV 下载失败：' . ($download['error'] ?? '未知错误'));
            }
            $this->redirect('/jobs/' . $job['id']);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'atlas-csv-');
        file_put_contents($tmp, $download['body']);
        $result = (new CsvImporter())->importPath($job['id'], $tmp, $job['scraper_job_id'] . '.csv');
        @unlink($tmp);
        if ($result['ok']) {
            $externalNote = '';
            if (!empty($job['scraper_job_id'])) {
                $deleteResult = $this->cancelExternalAfterImport($job);
                $externalNote = $deleteResult['ok'] ? '；外部任务已删除' : '；外部任务删除失败：' . ($deleteResult['error'] ?? '未知错误');
            }
            $this->flashSuccess("CSV 导入成功：{$result['rows_imported']} 行，新建 {$result['businesses_created']} 家商家{$externalNote}。");
        } else {
            $this->flashError($result['error'] ?? '导入失败。');
        }
        $this->redirect('/jobs/' . $job['id']);
    }

    public function uploadCsv(): string
    {
        $jobId = (string) $this->input('job_id');
        $job = $this->findJob($jobId);
        if (!$job) { $this->flashError('任务不存在。'); $this->redirect('/jobs'); }
        if (!isset($_FILES['csv']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flashError('请选择要上传的 CSV 文件。');
            $this->redirect('/jobs/' . $jobId);
        }
        if (($_FILES['csv']['size'] ?? 0) > CsvImporter::MAX_BYTES) {
            $this->flashError('CSV 超过 50 MB 上限。');
            $this->redirect('/jobs/' . $jobId);
        }
        $result = (new CsvImporter())->importPath($jobId, (string) $_FILES['csv']['tmp_name'], (string) $_FILES['csv']['name']);
        if ($result['ok']) {
            $externalNote = '';
            if (!empty($job['scraper_job_id'])) {
                $deleteResult = $this->cancelExternalAfterImport($job);
                $externalNote = $deleteResult['ok'] ? '；外部任务已删除' : '；外部任务删除失败：' . ($deleteResult['error'] ?? '未知错误');
            }
            $this->flashSuccess("CSV 导入完成：{$result['rows_imported']} 行，新建 {$result['businesses_created']} 家{$externalNote}。");
        } else {
            $this->flashError($result['error'] ?? 'CSV 导入失败。');
        }
        $this->redirect('/jobs/' . $jobId);
    }

    public function download(array $params): string
    {
        $job = $this->findJob($params['id']);
        if (!$job || empty($job['raw_csv_path'])) {
            http_response_code(404);
            return '原始 CSV 不存在。';
        }
        $relative = ltrim((string) $job['raw_csv_path'], '/');
        $path = base_path($relative);
        $real = realpath($path);
        $allowed = realpath(base_path('storage/raw'));
        if (!$real || !$allowed || !str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) || !is_file($real)) {
            http_response_code(404);
            return '原始 CSV 不存在。';
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($real) . '"');
        header('Content-Length: ' . filesize($real));
        readfile($real);
        exit;
    }

    public function batch(): string
    {
        $countries = db()->query('SELECT * FROM countries WHERE is_active = 1 ORDER BY name_en')->fetchAll();
        $regions = db()->query('SELECT r.* FROM regions r WHERE r.is_active = 1 ORDER BY r.name')->fetchAll();
        $industries = db()->query('SELECT * FROM industries WHERE is_active = 1 ORDER BY name')->fetchAll();
        $keywordsStmt = db()->prepare('SELECT k.*, c.iso2, c.name_en AS country_name, i.name AS industry_name FROM keywords k LEFT JOIN countries c ON c.id = k.country_id LEFT JOIN industries i ON i.id = k.industry_id WHERE k.user_id = ? AND k.is_active = 1 ORDER BY c.name_en, k.keyword');
        $keywordsStmt->execute([$this->userId()]);
        $keywords = $keywordsStmt->fetchAll();
        return $this->view('Admin/Jobs/View/batch', [
            'pageTitle' => '批量任务',
            'active' => 'jobs',
            'countries' => $countries,
            'regions' => $regions,
            'industries' => $industries,
            'keywords' => $keywords,
            'preview' => null,
            'selected' => [],
            'batchConfig' => null,
        ]);
    }

    public function batchPreview(): string
    {
        $seed = $this->batchSeed();
        if ($seed === null) {
            $this->redirect('/jobs/batch');
        }
        [$config, $preview] = $seed;
        $countries = db()->query('SELECT * FROM countries WHERE is_active = 1 ORDER BY name_en')->fetchAll();
        $regions = db()->query('SELECT r.* FROM regions r WHERE r.is_active = 1 ORDER BY r.name')->fetchAll();
        $industries = db()->query('SELECT * FROM industries WHERE is_active = 1 ORDER BY name')->fetchAll();
        $keywordsStmt = db()->prepare('SELECT k.*, c.iso2, c.name_en AS country_name, i.name AS industry_name FROM keywords k LEFT JOIN countries c ON c.id = k.country_id LEFT JOIN industries i ON i.id = k.industry_id WHERE k.user_id = ? AND k.is_active = 1 ORDER BY c.name_en, k.keyword');
        $keywordsStmt->execute([$this->userId()]);
        $keywords = $keywordsStmt->fetchAll();
        return $this->view('Admin/Jobs/View/batch', [
            'pageTitle' => '批量任务 · 预览',
            'active' => 'jobs',
            'countries' => $countries,
            'regions' => $regions,
            'industries' => $industries,
            'keywords' => $keywords,
            'preview' => $preview,
            'selected' => $config['keyword_ids'],
            'batchConfig' => $config,
        ]);
    }

    public function batchCreate(): string
    {
        $seed = $this->batchSeed();
        if ($seed === null) {
            $this->redirect('/jobs/batch');
        }
        [$config, $preview] = $seed;
        if (!$preview) {
            $this->flashError('没有可创建的有效任务。');
            $this->redirect('/jobs/batch');
        }
        if (count($preview) > 200) {
            $this->flashError('单批次最多 200 个子任务，请缩小关键词或范围。');
            $this->redirect('/jobs/batch');
        }
        $batchId = $this->uuid();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $insBatch = $pdo->prepare('INSERT INTO scrape_batches (id, user_id, name, status, total_jobs, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)');
            $insBatch->execute([$batchId, $this->userId(), $config['batch_name'], 'queued', count($preview), $config['notes'] ?? null, now_utc(), now_utc()]);
            $insJob = $pdo->prepare('INSERT INTO scrape_jobs (id, user_id, batch_id, country_id, region_id, industry_id, keyword_id, task_name, keyword_text, language_code, latitude, longitude, zoom, radius, depth, fastmode, extract_email, queue_order, max_time, status, target_address, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($preview as $item) {
                $insJob->execute([
                    $this->uuid(), $this->userId(), $batchId, $item['country_id'], $item['region_id'], $item['industry_id'],
                    $item['keyword_id'], $item['task_name'], $item['keyword'], $item['language_code'],
                    $item['latitude'], $item['longitude'], $config['zoom'], $config['radius'], $config['depth'],
                    $config['fastmode'] ? 1 : 0, $config['extract_email'] ? 1 : 0, $config['max_time'],
                    'queued', $item['target_address'], now_utc(), now_utc(),
                ]);
            }
            audit('batch.create', 'scrape_batch', $batchId, ['jobs' => count($preview)]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->flashError('批量创建失败：' . $e->getMessage());
            $this->redirect('/jobs/batch');
        }
        $this->flashSuccess('批次已创建并进入本地队列。外部 Scraper 提交仍需逐任务确认。');
        $this->redirect('/jobs?status=queued');
    }

    private function batchSeed(): ?array
    {
        $keywordIds = $_POST['keyword_ids'] ?? [];
        if (is_string($keywordIds)) {
            $decoded = json_decode($keywordIds, true);
            $keywordIds = is_array($decoded) ? $decoded : explode(',', $keywordIds);
        }
        $keywordIds = array_values(array_unique(array_filter(array_map('intval', (array) $keywordIds))));
        if (!$keywordIds) {
            $this->flashError('请至少选择一个关键词。');
            return null;
        }
        $placeholders = implode(',', array_fill(0, count($keywordIds), '?'));
        $stmt = db()->prepare("SELECT k.*, c.name_en AS country_name, c.iso2 AS country_iso2, c.center_lat, c.center_lng, c.default_zoom AS country_zoom, i.name AS industry_name
            FROM keywords k
            LEFT JOIN countries c ON c.id = k.country_id
            LEFT JOIN industries i ON i.id = k.industry_id
            WHERE k.id IN ({$placeholders}) AND k.user_id = ? AND k.is_active = 1
            ORDER BY c.name_en, k.keyword");
        $params = $keywordIds;
        $params[] = $this->userId();
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $countryId = $this->integer('country_id');
        $regionId = $this->integer('region_id');
        $region = null;
        if ($regionId) {
            $s = db()->prepare('SELECT * FROM regions WHERE id = ?');
            $s->execute([$regionId]);
            $region = $s->fetch() ?: null;
        }
        $country = null;
        if ($countryId) {
            $s = db()->prepare('SELECT * FROM countries WHERE id = ?');
            $s->execute([$countryId]);
            $country = $s->fetch() ?: null;
        }

        $regionStmt = db()->prepare('SELECT * FROM regions WHERE country_id = ? AND is_active = 1 ORDER BY length(name) DESC');
        $countryStmt = db()->prepare('SELECT * FROM countries WHERE id = ?');

        $config = [
            'batch_name' => trim((string) $this->input('batch_name')) ?: ('Batch ' . gmdate('Y-m-d H:i')),
            'notes' => $this->input('notes') ?: null,
            'country_id' => $countryId ?: null,
            'region_id' => $regionId ?: null,
            'zoom' => max(1, min(20, $this->integer('zoom', 13))),
            'radius' => max(500, min(50000, $this->integer('radius', 10000))),
            'depth' => max(1, min(50, $this->integer('depth', 10))),
            'fastmode' => (int) app_setting('fastmode_default', '0') === 1,
            'extract_email' => $this->boolean('extract_email'),
            'max_time' => preg_replace('/[^0-9a-zA-Z.]/', '', (string) $this->input('max_time', '10m')) ?: '10m',
            'keyword_ids' => array_map(static fn ($r) => (int) $r['id'], $rows),
        ];

        $preview = [];
        foreach ($rows as $row) {
            $ctryId = $countryId ?: ((int) ($row['country_id'] ?: 0) ?: null);
            $ctry = $country;
            if (!$ctry && $ctryId) {
                $countryStmt->execute([$ctryId]);
                $ctry = $countryStmt->fetch() ?: null;
            }
            if (!$ctry && $row['country_iso2']) {
                $ctry = [
                    'id' => $ctryId,
                    'name_en' => $row['country_name'],
                    'iso2' => $row['country_iso2'],
                    'center_lat' => $row['center_lat'],
                    'center_lng' => $row['center_lng'],
                    'default_zoom' => $row['country_zoom'],
                ];
            }

            // A specific city node is used when the user locks one, otherwise
            // match a seeded city name inside the keyword; no arbitrary region.
            $activeRegion = $region;
            if (!$activeRegion && $ctryId) {
                $regionStmt->execute([$ctryId]);
                foreach ($regionStmt->fetchAll() as $candidate) {
                    if (mb_stripos((string) $row['keyword'], (string) $candidate['name']) !== false) {
                        $activeRegion = $candidate;
                        break;
                    }
                }
            }

            $lat = $activeRegion['latitude'] ?? $ctry['center_lat'] ?? $row['center_lat'] ?? null;
            $lng = $activeRegion['longitude'] ?? $ctry['center_lng'] ?? $row['center_lng'] ?? null;
            if ($lat === null || $lng === null) continue;
            $countryName = $ctry['name_en'] ?? $row['country_name'] ?? '—';
            $regionName = $activeRegion['name'] ?? '国家中心点';
            $preview[] = [
                'keyword_id' => (int) $row['id'],
                'keyword' => $row['keyword'],
                'language_code' => $row['language_code'],
                'country_id' => $ctryId,
                'country_name' => $countryName,
                'country_iso2' => $ctry['iso2'] ?? $row['country_iso2'] ?? '',
                'region_id' => $activeRegion ? (int) $activeRegion['id'] : null,
                'region_name' => $regionName,
                'industry_id' => $row['industry_id'] ? (int) $row['industry_id'] : null,
                'industry_name' => $row['industry_name'] ?? '未分类',
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
                'zoom' => (int) ($activeRegion['default_zoom'] ?? $ctry['default_zoom'] ?? $config['zoom']),
                'task_name' => mb_substr($config['batch_name'] . ' · ' . $row['keyword'], 0, 180),
                'target_address' => trim($regionName . ', ' . $countryName, ', '),
            ];
        }
        return [$config, $preview];
    }

    private function submitJob(string $id): array
    {
        $job = $this->findJob($id);
        if (!$job) return ['ok' => false, 'error' => '任务不存在'];
        $payload = [
            'name' => $job['task_name'],
            'keywords' => $job['keyword_text'],
            'lang' => $job['language_code'],
            'zoom' => (string) $job['zoom'],
            'latitude' => (string) $job['latitude'],
            'longitude' => (string) $job['longitude'],
            'radius' => (string) $job['radius'],
            'depth' => (string) $job['depth'],
            'maxtime' => $job['max_time'],
        ];
        if ((int) $job['fastmode'] === 1) $payload['fastmode'] = 'on';
        if ((int) $job['extract_email'] === 1) $payload['email'] = 'on';

        db()->prepare('UPDATE scrape_jobs SET status = ?, updated_at = ? WHERE id = ? AND user_id = ?')->execute(['submitting', now_utc(), $id, $this->userId()]);
        $result = (new ScraperClient())->submit($payload);
        if ($result['ok'] && $result['job_id']) {
            db()->prepare('UPDATE scrape_jobs SET status = ?, scraper_job_id = ?, external_status = ?, submitted_at = ?, error_message = ?, updated_at = ? WHERE id = ? AND user_id = ?')
                ->execute(['submitted', $result['job_id'], 'submitted', now_utc(), null, now_utc(), $id, $this->userId()]);
            audit('job.submit', 'scrape_job', $id, ['scraper_job_id' => $result['job_id']]);
            $this->flashSuccess('任务已提交至 Scraper，外部 ID：' . $result['job_id']);
            return ['ok' => true];
        }
        $state = $result['state'] === 'submission_unknown' ? 'submission_unknown' : 'failed';
        $message = $result['error'] ?: 'Scraper 已响应，但未能可靠解析任务 ID。请核对外部任务列表。';
        if ($result['ok'] && !$result['job_id']) {
            $state = 'submission_unknown';
        }
        db()->prepare('UPDATE scrape_jobs SET status = ?, error_message = ?, external_status = ?, submitted_at = ?, updated_at = ? WHERE id = ? AND user_id = ?')
            ->execute([$state, mb_substr($message, 0, 1000), $result['state'], now_utc(), now_utc(), $id, $this->userId()]);
        audit('job.submit.' . $state, 'scrape_job', $id, ['http_status' => $result['http_status']]);
        if ($state === 'submission_unknown') {
            $this->flashError('提交结果未知：系统不会自动重试，请到 Scraper 任务列表核对。');
        } else {
            $this->flashError('任务提交失败：' . $message);
        }
        return ['ok' => false, 'error' => $message];
    }

    private function findJob(string $id): ?array
    {
        $stmt = db()->prepare('SELECT j.*, c.iso2, c.name_en AS country_name, c.name_local AS country_local,
                r.name AS region_name, i.name AS industry_name, k.keyword AS keyword_label, b.name AS batch_name, b.queue_mode AS batch_queue_mode, b.queue_paused AS batch_queue_paused
            FROM scrape_jobs j
            LEFT JOIN countries c ON c.id = j.country_id
            LEFT JOIN regions r ON r.id = j.region_id
            LEFT JOIN industries i ON i.id = j.industry_id
            LEFT JOIN keywords k ON k.id = j.keyword_id
            LEFT JOIN scrape_batches b ON b.id = j.batch_id AND b.user_id = j.user_id
            WHERE j.id = ? AND j.user_id = ?');
        $stmt->execute([$id, $this->userId()]);
        return $stmt->fetch() ?: null;
    }

    private function resolveRegionSelection(): ?int
    {
        $raw = trim((string) $this->input('region_id', ''));
        if ($raw === '') return null;
        if (ctype_digit($raw)) return (int) $raw;
        if (!preg_match('/^geo:(\d+)$/', $raw, $match)) return null;
        $city = (new GeoCities())->find((int) $match[1]);
        if (!$city) return null;
        $countryStmt = db()->prepare('SELECT id FROM countries WHERE iso2 = ?');
        $countryStmt->execute([$city['country_code']]);
        $countryId = (int) ($countryStmt->fetchColumn() ?: 0);
        if (!$countryId) return null;
        $existing = db()->prepare('SELECT id FROM regions WHERE country_id = ? AND lower(name) = lower(?) LIMIT 1');
        $existing->execute([$countryId, $city['name']]);
        $existingId = $existing->fetchColumn();
        if ($existingId) return (int) $existingId;
        $insert = db()->prepare('INSERT INTO regions (country_id, region_type, name, name_local, latitude, longitude, default_zoom, is_active, created_at) VALUES (?,?,?,?,?,?,?,1,?)');
        $insert->execute([
            $countryId,
            'city',
            $city['name'],
            $city['name_zh'] ?: $city['name'],
            $city['latitude'],
            $city['longitude'],
            12,
            now_utc(),
        ]);
        return (int) db()->lastInsertId();
    }

    private function cleanupEmptyBatches(int $userId): void
    {
        db()->prepare('DELETE FROM scrape_batches WHERE user_id = ? AND NOT EXISTS (SELECT 1 FROM scrape_jobs j WHERE j.batch_id = scrape_batches.id)')
            ->execute([$userId]);
    }

    private function advanceCoverageQueue(string $batchId): ?string
    {
        $batchStmt = db()->prepare('SELECT queue_mode, queue_paused FROM scrape_batches WHERE id = ? AND user_id = ?');
        $batchStmt->execute([$batchId, $this->userId()]);
        $batch = $batchStmt->fetch();
        if (!$batch || (int) $batch['queue_mode'] !== 1 || (int) $batch['queue_paused'] === 1) {
            return null;
        }
        $activeStmt = db()->prepare("SELECT COUNT(*) FROM scrape_jobs WHERE batch_id = ? AND user_id = ? AND status IN ('running', 'submitting', 'submitted')");
        $activeStmt->execute([$batchId, $this->userId()]);
        if ((int) $activeStmt->fetchColumn() > 0) {
            return null;
        }
        $nextStmt = db()->prepare("SELECT id FROM scrape_jobs WHERE batch_id = ? AND user_id = ? AND status IN ('queued', 'draft') ORDER BY queue_order ASC, created_at ASC LIMIT 1");
        $nextStmt->execute([$batchId, $this->userId()]);
        $candidate = (string) ($nextStmt->fetchColumn() ?: '');
        if ($candidate === '') return null;
        $claim = db()->prepare("UPDATE scrape_jobs SET status = 'submitting', updated_at = ? WHERE id = ? AND user_id = ? AND status IN ('queued', 'draft')");
        $claim->execute([now_utc(), $candidate, $this->userId()]);
        if ($claim->rowCount() !== 1) return null;
        $this->submitJob($candidate);
        return $candidate;
    }

    public function toggleQueueBatch(array $params): string
    {
        $batchId = (string) ($params['id'] ?? '');
        $stmt = db()->prepare('SELECT * FROM scrape_batches WHERE id = ? AND user_id = ? AND queue_mode = 1');
        $stmt->execute([$batchId, $this->userId()]);
        $batch = $stmt->fetch();
        if (!$batch) {
            $this->flashError('自动队列不存在。');
            $this->redirect('/jobs');
        }
        $paused = (int) $batch['queue_paused'] === 1;
        db()->prepare('UPDATE scrape_batches SET queue_paused = ?, updated_at = ? WHERE id = ? AND user_id = ?')
            ->execute([$paused ? 0 : 1, now_utc(), $batchId, $this->userId()]);
        if (!$paused) {
            $this->flashSuccess('自动队列已暂停；正在运行的任务不会中断，后续任务不再自动提交。');
            $this->redirect('/jobs');
        }
        $this->flashSuccess('自动队列已继续。');
        $this->advanceCoverageQueue($batchId);
        $this->redirect('/jobs');
    }

    public function deleteBatch(array $params): string
    {
        $batchId = (string) ($params['id'] ?? '');
        $stmt = db()->prepare('SELECT * FROM scrape_batches WHERE id = ? AND user_id = ?');
        $stmt->execute([$batchId, $this->userId()]);
        $batch = $stmt->fetch();
        if (!$batch) {
            $this->flashError('批次不存在。');
            $this->redirect('/jobs');
        }
        $jobsStmt = db()->prepare('SELECT id, batch_id, status, result_count, raw_csv_path, scraper_job_id, task_name FROM scrape_jobs WHERE batch_id = ? AND user_id = ?');
        $jobsStmt->execute([$batchId, $this->userId()]);
        $jobs = $jobsStmt->fetchAll();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $deleted = 0;
            $raw = 0;
            $businesses = 0;
            foreach ($jobs as $job) {
                $result = $this->deleteJobRow($job, $pdo);
                $deleted++;
                $raw += $result['raw_records'];
                $businesses += $result['deleted_businesses'];
            }
            audit('batch.delete', 'scrape_batch', $batchId, ['tasks' => $deleted, 'raw_records' => $raw, 'businesses' => $businesses]);
            $pdo->commit();
            $this->cleanupEmptyBatches($this->userId());
            $this->flashSuccess("已删除整个队列：{$deleted} 个任务，清理 {$raw} 条原始记录、{$businesses} 家仅属于这些任务的商家；外部 Scraper 任务不会被删除。");
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->flashError('删除队列失败：' . $e->getMessage());
            $this->redirect('/jobs');
        }
        $this->redirect('/jobs');
    }

    public function cancelAllBatch(array $params): string
    {
        $batchId = (string) ($params['id'] ?? '');
        $stmt = db()->prepare('SELECT * FROM scrape_batches WHERE id = ? AND user_id = ?');
        $stmt->execute([$batchId, $this->userId()]);
        $batch = $stmt->fetch();
        if (!$batch) {
            $this->flashError('批次不存在。');
            $this->redirect('/jobs');
        }
        $pdo = db();
        $pdo->prepare('UPDATE scrape_batches SET queue_paused = 1, updated_at = ? WHERE id = ? AND user_id = ?')
            ->execute([now_utc(), $batchId, $this->userId()]);
        $stmt = $pdo->prepare("SELECT id, scraper_job_id FROM scrape_jobs WHERE batch_id = ? AND user_id = ? AND status NOT IN ('completed', 'partial', 'cancelled') ORDER BY queue_order ASC");
        $stmt->execute([$batchId, $this->userId()]);
        $rows = $stmt->fetchAll();
        $cancelled = 0;
        $externalFailures = 0;
        foreach ($rows as $row) {
            $externalError = null;
            if (!empty($row['scraper_job_id'])) {
                $result = (new ScraperClient())->cancel((string) $row['scraper_job_id']);
                if (!$result['ok']) { $externalError = $result['error'] ?? '外部取消失败'; $externalFailures++; }
            }
            $pdo->prepare("UPDATE scrape_jobs SET status = 'cancelled', error_message = ?, external_status = ?, updated_at = ? WHERE id = ? AND user_id = ?")
                ->execute([
                    $externalError ? '整批取消；外部取消未确认：' . $externalError : '用户整批取消',
                    $externalError ? 'cancel_unconfirmed' : 'cancelled',
                    now_utc(), $row['id'], $this->userId(),
                ]);
            $cancelled++;
        }
        audit('job.cancel_all', 'scrape_batch', $batchId, ['cancelled' => $cancelled, 'external_failures' => $externalFailures]);
        $this->flashSuccess('整批取消完成：已取消 ' . $cancelled . ' 个未完成任务' . ($externalFailures ? '；其中 ' . $externalFailures . ' 个外部任务取消未确认。' : '。'));
        $this->redirect('/jobs?status=cancelled');
    }

    public function toggleQueue(array $params): string
    {
        $job = $this->findJob($params['id']);
        if (!$job || empty($job['batch_id']) || (int) ($job['batch_queue_mode'] ?? 0) !== 1) {
            $this->flashError('该任务不属于自动队列。');
            $this->redirect($job ? '/jobs/' . $job['id'] : '/jobs');
        }
        $paused = (int) ($job['batch_queue_paused'] ?? 0) === 1;
        db()->prepare('UPDATE scrape_batches SET queue_paused = ?, updated_at = ? WHERE id = ? AND user_id = ?')
            ->execute([$paused ? 0 : 1, now_utc(), $job['batch_id'], $this->userId()]);
        if (!$paused) {
            $this->flashSuccess('自动队列已暂停；正在运行的任务不会中断，后续任务不再自动提交。');
            $this->redirect('/jobs/' . $job['id']);
        }
        $this->flashSuccess('自动队列已继续。');
        $nextId = $this->advanceCoverageQueue($job['batch_id']);
        $this->redirect($nextId ? '/jobs/' . $nextId : '/jobs/' . $job['id']);
    }

    public function cancel(array $params): string
    {
        $job = $this->findJob($params['id']);
        if (!$job) {
            $this->flashError('任务不存在。');
            $this->redirect('/jobs');
        }
        if (in_array($job['status'], ['completed', 'partial', 'cancelled'], true)) {
            $this->flashError('已完成、部分导入或已取消的任务不能再次取消。');
            $this->redirect('/jobs/' . $job['id']);
        }
        $externalError = null;
        if (!empty($job['scraper_job_id'])) {
            $result = (new ScraperClient())->cancel($job['scraper_job_id']);
            if (!$result['ok']) $externalError = $result['error'] ?? '外部取消失败';
        }
        db()->prepare("UPDATE scrape_jobs SET status = 'cancelled', error_message = ?, external_status = ?, updated_at = ? WHERE id = ? AND user_id = ?")
            ->execute([$externalError ? '用户取消；外部取消未确认：' . $externalError : '用户取消', $externalError ? 'cancel_unconfirmed' : 'cancelled', now_utc(), $job['id'], $this->userId()]);
        if (!empty($job['batch_id'])) {
            db()->prepare('UPDATE scrape_batches SET queue_paused = 1, updated_at = ? WHERE id = ? AND user_id = ? AND queue_mode = 1')
                ->execute([now_utc(), $job['batch_id'], $this->userId()]);
        }
        audit('job.cancel', 'scrape_job', $job['id'], ['external_error' => $externalError]);
        $this->flashSuccess($externalError ? '本地任务已取消；外部任务取消失败，请到 Scraper 核对。' : '任务已取消。');
        $this->redirect('/jobs/' . $job['id']);
    }

    public function cancelAll(array $params): string
    {
        $job = $this->findJob($params['id']);
        if (!$job || empty($job['batch_id'])) {
            $this->flashError('该任务不属于批次。');
            $this->redirect($job ? '/jobs/' . $job['id'] : '/jobs');
        }
        $pdo = db();
        $pdo->prepare('UPDATE scrape_batches SET queue_paused = 1, updated_at = ? WHERE id = ? AND user_id = ?')
            ->execute([now_utc(), $job['batch_id'], $this->userId()]);
        $stmt = $pdo->prepare("SELECT id, scraper_job_id FROM scrape_jobs WHERE batch_id = ? AND user_id = ? AND status NOT IN ('completed', 'partial', 'cancelled') ORDER BY queue_order ASC");
        $stmt->execute([$job['batch_id'], $this->userId()]);
        $rows = $stmt->fetchAll();
        $cancelled = 0;
        $externalFailures = 0;
        foreach ($rows as $row) {
            $externalError = null;
            if (!empty($row['scraper_job_id'])) {
                $result = (new ScraperClient())->cancel((string) $row['scraper_job_id']);
                if (!$result['ok']) { $externalError = $result['error'] ?? '外部取消失败'; $externalFailures++; }
            }
            $pdo->prepare("UPDATE scrape_jobs SET status = 'cancelled', error_message = ?, external_status = ?, updated_at = ? WHERE id = ? AND user_id = ?")
                ->execute([
                    $externalError ? '整批取消；外部取消未确认：' . $externalError : '用户整批取消',
                    $externalError ? 'cancel_unconfirmed' : 'cancelled',
                    now_utc(), $row['id'], $this->userId(),
                ]);
            $cancelled++;
        }
        audit('job.cancel_all', 'scrape_batch', $job['batch_id'], ['cancelled' => $cancelled, 'external_failures' => $externalFailures]);
        $this->flashSuccess('整批取消完成：已取消 ' . $cancelled . ' 个未完成任务' . ($externalFailures ? '；其中 ' . $externalFailures . ' 个外部任务取消未确认。' : '。'));
        $this->redirect('/jobs?status=cancelled');
    }

    private function coverageGridPoints(float $lat, float $lng, int $radiusMeters, int $cellMeters): array
    {
        $spacing = $cellMeters * 1.4;
        if ($spacing <= 0) return [];
        $latStep = $spacing / 111320.0;
        $lngStep = $spacing / (111320.0 * max(0.1, cos(deg2rad($lat))));
        $steps = (int) ceil($radiusMeters / $spacing);
        $points = [];
        $seen = [];
        for ($x = -$steps; $x <= $steps; $x++) {
            for ($y = -$steps; $y <= $steps; $y++) {
                $distance = sqrt(($x * $spacing) ** 2 + ($y * $spacing) ** 2);
                if ($distance > $radiusMeters) continue;
                $pointLat = round($lat + ($y * $latStep), 6);
                $pointLng = round($lng + ($x * $lngStep), 6);
                $key = $pointLat . ',' . $pointLng;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $points[] = [
                    'lat' => $pointLat,
                    'lng' => $pointLng,
                    'distance' => $distance,
                ];
            }
        }
        usort($points, static function (array $a, array $b): int {
            return ($a['distance'] <=> $b['distance']) ?: (($a['lat'] <=> $b['lat']) ?: ($a['lng'] <=> $b['lng']));
        });
        return $points;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
