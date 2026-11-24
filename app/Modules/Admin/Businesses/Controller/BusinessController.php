<?php
declare(strict_types=1);

namespace App\Modules\Admin\Businesses\Controller;

use App\Core\Controller;
use App\Services\WebsiteEmailFinder;

final class BusinessController extends Controller
{
    public function index(): string
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $countryId = (int) ($_GET['country'] ?? 0);
        $industryId = (int) ($_GET['industry'] ?? 0);
        $hasPhone = (string) ($_GET['has_phone'] ?? '');
        $hasWebsite = (string) ($_GET['has_website'] ?? '');
        $hasEmail = (string) ($_GET['has_email'] ?? '');
        $ratingMin = trim((string) ($_GET['rating_min'] ?? ''));
        $completenessMin = (int) ($_GET['completeness_min'] ?? 0);
        $sort = (string) ($_GET['sort'] ?? 'newest');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;

        $uid = $this->userId();
        $where = ['b.user_id = ?'];
        $params = [$uid];
        if ($q !== '') {
            $hasCjk = preg_match('/[\x{4e00}-\x{9fff}]/u', $q) === 1;
            $ftQuery = trim((string) preg_replace('/[+\-<>()~*\"@]+/u', ' ', $q));
            if (!$hasCjk && mb_strlen($ftQuery) >= 3) {
                $where[] = 'MATCH(b.business_name, b.address, b.website, b.phone) AGAINST (? IN BOOLEAN MODE)';
                $params[] = $ftQuery;
            } else {
                $where[] = '(b.business_name LIKE ? OR b.address LIKE ? OR b.website LIKE ? OR b.phone LIKE ?)';
                array_push($params, "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%");
            }
        }
        if ($countryId) { $where[] = 'b.country_id = ?'; $params[] = $countryId; }
        if ($industryId) { $where[] = 'b.industry_id = ?'; $params[] = $industryId; }
        if ($hasPhone === '1') $where[] = "b.phone IS NOT NULL AND b.phone <> ''";
        if ($hasPhone === '0') $where[] = "(b.phone IS NULL OR b.phone = '')";
        if ($hasWebsite === '1') $where[] = "b.website IS NOT NULL AND b.website <> ''";
        if ($hasWebsite === '0') $where[] = "(b.website IS NULL OR b.website = '')";
        if ($hasEmail === '1') $where[] = "(b.email_status = 'found' OR EXISTS (SELECT 1 FROM business_contacts bc WHERE bc.business_id = b.id AND bc.contact_type = 'email'))";
        if ($hasEmail === '0') $where[] = "b.email_status <> 'found' AND NOT EXISTS (SELECT 1 FROM business_contacts bc WHERE bc.business_id = b.id AND bc.contact_type = 'email')";
        if ($ratingMin !== '' && is_numeric($ratingMin)) { $where[] = 'b.rating >= ?'; $params[] = (float) $ratingMin; }
        if ($completenessMin > 0) { $where[] = 'b.completeness_score >= ?'; $params[] = $completenessMin; }

        $orderMap = [
            'newest' => 'b.created_at DESC',
            'name' => 'b.business_name ASC',
            'rating' => 'b.rating IS NULL ASC, b.rating DESC',
            'reviews' => 'b.review_count IS NULL ASC, b.review_count DESC',
            'completeness' => 'b.completeness_score DESC',
            'updated' => 'b.updated_at DESC',
        ];
        $order = $orderMap[$sort] ?? $orderMap['newest'];
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $countStmt = db()->prepare("SELECT COUNT(*) FROM businesses b {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));

        $sql = "SELECT b.*, c.iso2, c.name_en AS country_name, r.name AS region_name, i.name AS industry_name,
                    (SELECT COUNT(*) FROM business_contacts bc WHERE bc.business_id = b.id AND bc.contact_type = 'email') AS email_count,
                    (SELECT COUNT(*) FROM business_sources bs WHERE bs.business_id = b.id) AS source_count
                FROM businesses b
                LEFT JOIN countries c ON c.id = b.country_id
                LEFT JOIN regions r ON r.id = b.region_id
                LEFT JOIN industries i ON i.id = b.industry_id
                {$whereSql}
                ORDER BY {$order}
                LIMIT ? OFFSET ?";
        $stmt = db()->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p, is_float($p) ? \PDO::PARAM_STR : (is_int($p) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
        }
        $stmt->bindValue($i++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($i, ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();
        $businesses = $stmt->fetchAll();

        $countries = db()->query('SELECT id, iso2, name_en FROM countries ORDER BY name_en')->fetchAll();
        $industries = db()->query('SELECT id, name FROM industries WHERE is_active = 1 ORDER BY name')->fetchAll();
        $summaryStmt = db()->prepare("SELECT COUNT(*) total,
            SUM(CASE WHEN email_status = 'found' THEN 1 ELSE 0 END) with_email,
            SUM(CASE WHEN latitude IS NOT NULL THEN 1 ELSE 0 END) with_coords,
            COALESCE(AVG(completeness_score), 0) avg_completeness
            FROM businesses WHERE user_id = ?");
        $summaryStmt->execute([$uid]);
        $summary = $summaryStmt->fetch();
        return $this->view('Admin/Businesses/View/index', [
            'pageTitle' => '商家资料库',
            'active' => 'businesses',
            'businesses' => $businesses,
            'countries' => $countries,
            'industries' => $industries,
            'filters' => compact('q', 'countryId', 'industryId', 'hasPhone', 'hasWebsite', 'hasEmail', 'ratingMin', 'completenessMin', 'sort', 'page'),
            'page' => $page,
            'total' => $total,
            'pages' => $pages,
            'perPage' => $perPage,
            'summary' => $summary,
        ]);
    }

    public function show(array $params): string
    {
        $stmt = db()->prepare('SELECT b.*, c.iso2, c.name_en AS country_name, c.name_local AS country_local,
                r.name AS region_name, i.name AS industry_name
            FROM businesses b
            LEFT JOIN countries c ON c.id = b.country_id
            LEFT JOIN regions r ON r.id = b.region_id
            LEFT JOIN industries i ON i.id = b.industry_id
            WHERE b.id = ? AND b.user_id = ?');
        $stmt->execute([(int) $params['id'], $this->userId()]);
        $business = $stmt->fetch();
        if (!$business) {
            http_response_code(404);
            return $this->view('Common/View/error', ['pageTitle' => '商家不存在', 'code' => '404', 'message' => '该商家记录不存在。']);
        }
        $contacts = db()->prepare('SELECT * FROM business_contacts WHERE business_id = ? ORDER BY is_primary DESC, contact_type, discovered_at DESC');
        $contacts->execute([$business['id']]);
        $contactRows = $contacts->fetchAll();
        $sources = db()->prepare('SELECT bs.*, rr.row_no, rr.external_data_id, rr.imported_at, j.task_name, j.keyword_text, j.id AS job_id
            FROM business_sources bs
            JOIN raw_records rr ON rr.id = bs.raw_record_id
            JOIN scrape_jobs j ON j.id = bs.job_id
            WHERE bs.business_id = ?
            ORDER BY bs.last_seen_at DESC');
        $sources->execute([$business['id']]);
        $sourceRows = $sources->fetchAll();
        $candidates = db()->prepare('SELECT b.id, b.business_name, b.city, b.phone, b.website_domain, b.completeness_score, b.rating
            FROM businesses b
            WHERE b.id <> ? AND b.user_id = ? AND (
                (b.normalized_phone IS NOT NULL AND b.normalized_phone = ?) OR
                (b.website_domain IS NOT NULL AND b.website_domain = ?)
            ) LIMIT 8');
        $candidates->execute([$business['id'], $this->userId(), $business['normalized_phone'], $business['website_domain']]);
        $candidateRows = $candidates->fetchAll();
        $raw = db()->prepare('SELECT rr.* FROM raw_records rr JOIN business_sources bs ON bs.raw_record_id = rr.id WHERE bs.business_id = ? ORDER BY rr.imported_at DESC LIMIT 1');
        $raw->execute([$business['id']]);
        $rawRecord = $raw->fetch() ?: null;
        $countries = db()->query('SELECT id, iso2, name_en FROM countries ORDER BY name_en')->fetchAll();
        $industries = db()->query('SELECT id, name FROM industries WHERE is_active = 1 ORDER BY name')->fetchAll();
        $regions = db()->query('SELECT id, name, country_id FROM regions ORDER BY name')->fetchAll();

        return $this->view('Admin/Businesses/View/show', [
            'pageTitle' => $business['business_name'],
            'active' => 'businesses',
            'business' => $business,
            'contacts' => $contactRows,
            'sources' => $sourceRows,
            'candidates' => $candidateRows,
            'rawRecord' => $rawRecord,
            'countries' => $countries,
            'industries' => $industries,
            'regions' => $regions,
            'mapConfig' => config('map'),
            'useMap' => true,
            'emailFinderEnabled' => (string) app_setting('email_finder_enabled', '0') === '1',
        ]);
    }

    public function enrichEmail(array $params): string
    {
        $id = (int) $params['id'];
        $finder = new WebsiteEmailFinder();
        if (!$finder->isEnabled()) {
            $this->flashError('邮箱补全模块未启用，请先在系统设置中开启。');
            $this->redirect('/businesses/' . $id);
        }
        @set_time_limit(120);
        try {
            $result = $finder->findForBusiness($id);
        } catch (\Throwable $e) {
            audit('business.email_enrich.error', 'business', (string) $id, ['error' => $e->getMessage()]);
            $this->flashError('邮箱补全失败：' . $e->getMessage());
            $this->redirect('/businesses/' . $id);
        }
        if (($result['status'] ?? '') === 'found') {
            $this->flashSuccess($result['message'] . '，已保存来源 URL，验证状态为「未验证」。');
        } elseif (($result['status'] ?? '') === 'none_found') {
            $this->flashWarning('已检查 ' . (int) ($result['pages'] ?? 0) . ' 个公开页面，未发现邮箱。已标记为“未发现”。');
        } elseif (($result['status'] ?? '') === 'blocked') {
            $this->flashWarning($result['message']);
        } else {
            $this->flashError($result['message'] ?? '邮箱补全失败。');
        }
        $this->redirect('/businesses/' . $id);
    }

    public function enrichEmails(): string
    {
        $finder = new WebsiteEmailFinder();
        if (!$finder->isEnabled()) {
            $this->flashError('邮箱补全模块未启用，请先在系统设置中开启。');
            $this->redirect('/data-quality');
        }
        @set_time_limit(300);
        $stmt = db()->prepare("SELECT id FROM businesses WHERE user_id = ? AND website IS NOT NULL AND website <> '' AND email_status <> 'found' ORDER BY updated_at ASC LIMIT 3");
        $stmt->execute([$this->userId()]);
        $ids = array_map(static fn ($row) => (int) $row['id'], $stmt->fetchAll());
        if (!$ids) {
            $this->flashWarning('没有需要补全邮箱的商家，或所有含官网的商家都已检查过。');
            $this->redirect('/data-quality');
        }
        $found = 0;
        $none = 0;
        $blocked = 0;
        $failed = 0;
        foreach ($ids as $id) {
            try {
                $result = $finder->findForBusiness($id);
                match ($result['status'] ?? 'failed') {
                    'found' => $found++,
                    'none_found' => $none++,
                    'blocked' => $blocked++,
                    default => $failed++,
                };
            } catch (\Throwable $e) {
                $failed++;
                audit('business.email_enrich.error', 'business', (string) $id, ['error' => $e->getMessage()]);
            }
        }
        audit('business.email_enrich.batch', 'business', null, ['processed' => count($ids), 'found' => $found, 'none' => $none, 'blocked' => $blocked, 'failed' => $failed]);
        $this->flashSuccess("邮箱补全完成：处理 " . count($ids) . " 家，发现 {$found} 家，未发现 {$none} 家，阻止 {$blocked} 家，失败 {$failed} 家。");
        if ($blocked > 0 || $failed > 0) {
            $this->flashWarning('部分网站未能访问，已按 blocked / failed 状态记录，可稍后重试。');
        }
        $this->redirect('/data-quality');
    }

    public function update(array $params): string
    {
        $id = (int) $params['id'];
        $stmt = db()->prepare('SELECT * FROM businesses WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $this->userId()]);
        $business = $stmt->fetch();
        if (!$business) { $this->flashError('商家不存在。'); $this->redirect('/businesses'); }

        $name = trim((string) $this->input('business_name'));
        if ($name === '') { $this->flashError('商家名称不能为空。'); $this->redirect('/businesses/' . $id); }
        $update = db()->prepare('UPDATE businesses SET business_name = ?, normalized_name = ?, industry_id = ?, country_id = ?, region_id = ?, city = ?, state_region = ?, postal_code = ?, address = ?, phone = ?, normalized_phone = ?, website = ?, website_domain = ?, description = ?, email_status = ?, latitude = ?, longitude = ?, updated_at = ? WHERE id = ? AND user_id = ?');
        $website = trim((string) $this->input('website')) ?: null;
        $domain = $website ? parse_url(str_starts_with($website, 'http') ? $website : 'https://' . $website, PHP_URL_HOST) : null;
        $phone = trim((string) $this->input('phone')) ?: null;
        $normalizedPhone = $phone ? '+' . (preg_replace('/\D+/', '', $phone) ?: '') : null;
        $description = trim((string) $this->input('description'));
        $description = $description !== '' ? mb_substr($description, 0, 5000) : null;
        $update->execute([
            $name,
            mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? $name)),
            $this->integer('industry_id') ?: null,
            $this->integer('country_id') ?: null,
            $this->integer('region_id') ?: null,
            $this->input('city') ?: null,
            $this->input('state_region') ?: null,
            $this->input('postal_code') ?: null,
            $this->input('address') ?: null,
            $phone,
            $normalizedPhone,
            $website,
            $domain ? mb_strtolower(preg_replace('/^www\./', '', $domain)) : null,
            $description,
            in_array($this->input('email_status'), ['not_checked', 'found', 'none_found', 'failed', 'blocked'], true) ? $this->input('email_status') : 'not_checked',
            $this->float('latitude') ?: null,
            $this->float('longitude') ?: null,
            now_utc(),
            $id,
            $this->userId(),
        ]);
        $this->recomputeCompleteness($id);
        audit('business.update', 'business', (string) $id);
        $this->flashSuccess('商家资料已更新，原始采集记录保持不变。');
        $this->redirect('/businesses/' . $id);
    }

    public function updateEmailStatus(array $params): string
    {
        $id = (int) $params['id'];
        $status = (string) $this->input('email_status', 'not_checked');
        if (!in_array($status, ['not_checked', 'found', 'none_found', 'failed', 'blocked'], true)) $status = 'not_checked';
        db()->prepare('UPDATE businesses SET email_status = ?, updated_at = ? WHERE id = ? AND user_id = ?')->execute([$status, now_utc(), $id, $this->userId()]);
        $this->recomputeCompleteness($id);
        audit('business.email_status', 'business', (string) $id, ['status' => $status]);
        $this->flashSuccess('邮箱补全状态已更新。');
        $this->redirect('/businesses/' . $id);
    }

    private function recomputeCompleteness(int $id): void
    {
        recalculate_business_score($id);
    }

}
