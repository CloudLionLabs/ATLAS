<?php
declare(strict_types=1);

namespace App\Modules\Admin\Exports\Controller;

use App\Core\Controller;
use App\Services\XlsxExporter;

final class ExportController extends Controller
{
    public function index(): string
    {
        $uid = $this->userId();
        $historyStmt = db()->prepare('SELECT * FROM export_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 12');
        $historyStmt->execute([$uid]);
        $history = $historyStmt->fetchAll();
        $stats = db()->prepare("SELECT COUNT(*) total, SUM(CASE WHEN email_status = 'found' THEN 1 ELSE 0 END) with_email, COUNT(DISTINCT CASE WHEN country_id IS NOT NULL THEN country_id END) countries FROM businesses WHERE user_id = ?");
        $stats->execute([$uid]);
        $row = $stats->fetch() ?: [];
        $count = (int) ($row['total'] ?? 0);
        $withEmail = (int) ($row['with_email'] ?? 0);
        $countries = (int) ($row['countries'] ?? 0);

        // Only offer values that exist in this account's collected dataset.
        // Listing all 250 countries creates filters that can never return data.
        $countryOptions = db()->prepare("SELECT c.id, c.iso2, c.name_en, c.name_zh, COUNT(b.id) AS business_count
            FROM businesses b
            JOIN countries c ON c.id = b.country_id
            WHERE b.user_id = ? AND b.country_id IS NOT NULL
            GROUP BY c.id
            ORDER BY business_count DESC, c.name_en ASC");
        $countryOptions->execute([$uid]);
        $countryOptions = $countryOptions->fetchAll();

        $industryOptions = db()->prepare("SELECT i.id, i.name, COUNT(b.id) AS business_count
            FROM businesses b
            JOIN industries i ON i.id = b.industry_id
            WHERE b.user_id = ? AND b.industry_id IS NOT NULL
            GROUP BY i.id
            ORDER BY business_count DESC, i.name ASC");
        $industryOptions->execute([$uid]);
        $industryOptions = $industryOptions->fetchAll();

        $unassignedCountry = db()->prepare('SELECT COUNT(*) FROM businesses WHERE user_id = ? AND country_id IS NULL');
        $unassignedCountry->execute([$uid]);
        $unassignedCountry = (int) $unassignedCountry->fetchColumn();
        $unassignedIndustry = db()->prepare('SELECT COUNT(*) FROM businesses WHERE user_id = ? AND industry_id IS NULL');
        $unassignedIndustry->execute([$uid]);
        $unassignedIndustry = (int) $unassignedIndustry->fetchColumn();

        return $this->view('Admin/Exports/View/index', [
            'pageTitle' => '导出中心',
            'active' => 'exports',
            'history' => $history,
            'counts' => ['total' => $count, 'with_email' => $withEmail, 'countries' => $countries],
            'countries' => $countryOptions,
            'industries' => $industryOptions,
            'unassignedCountry' => $unassignedCountry,
            'unassignedIndustry' => $unassignedIndustry,
        ]);
    }

    public function csv(): string
    {
        $filters = $this->filters();
        [$headers, $rows] = $this->buildRows($filters);
        $filename = 'atlas-businesses-u' . $this->userId() . '-' . gmdate('Ymd-His') . '.csv';
        $path = base_path('storage/exports/' . $filename);
        $fh = fopen($path, 'wb');
        if ($fh === false) { $this->flashError('无法创建导出文件。'); $this->redirect('/exports'); }
        // UTF-8 BOM keeps Chinese readable in Excel.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $headers, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($fh, array_map('safe_csv_cell', $row), ',', '"', '\\');
        }
        fclose($fh);
        $this->logExport($filename, 'csv', count($rows), $filters);
        $this->flashSuccess('CSV 已生成：' . count($rows) . ' 行。公式注入防护已启用。');
        $this->redirect('/exports?file=' . urlencode($filename));
    }

    public function xlsx(): string
    {
        $filters = $this->filters();
        [$headers, $rows] = $this->buildRows($filters);
        $filename = 'atlas-businesses-u' . $this->userId() . '-' . gmdate('Ymd-His') . '.xlsx';
        $path = base_path('storage/exports/' . $filename);
        $ok = (new XlsxExporter())->write($path, $headers, $rows, 'Businesses');
        if (!$ok) { $this->flashError('XLSX 生成失败。'); $this->redirect('/exports'); }
        $this->logExport($filename, 'xlsx', count($rows), $filters);
        $this->flashSuccess('XLSX 已生成：' . count($rows) . ' 行。电话和邮编以文本保存。');
        $this->redirect('/exports?file=' . urlencode($filename));
    }

    public function download(array $params): string
    {
        $file = basename((string) $params['file']);
        if (!preg_match('/^atlas-businesses-u[0-9]+-[0-9]{8}-[0-9]{6}\.(csv|xlsx)$/', $file)) {
            http_response_code(404);
            return '文件不存在。';
        }
        $owner = db()->prepare('SELECT user_id FROM export_history WHERE filename = ? LIMIT 1');
        $owner->execute([$file]);
        if ((int) $owner->fetchColumn() !== $this->userId()) {
            http_response_code(404);
            return '文件不存在。';
        }
        $path = base_path('storage/exports/' . $file);
        $real = realpath($path);
        $allowed = realpath(base_path('storage/exports'));
        if (!$real || !$allowed || !str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) || !is_file($real)) {
            http_response_code(404);
            return '文件不存在。';
        }
        $mime = str_ends_with($file, '.xlsx')
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=utf-8';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($real));
        readfile($real);
        exit;
    }

    private function filters(): array
    {
        $where = ['b.user_id = ?'];
        $params = [$this->userId()];
        $country = (int) $this->input('country', 0);
        $industry = (int) $this->input('industry', 0);
        $hasEmail = (string) $this->input('has_email', '');
        $completeness = (int) $this->input('completeness_min', 0);
        if ($country === -1) {
            $where[] = 'b.country_id IS NULL';
        } elseif ($country > 0) {
            $where[] = 'b.country_id = ?';
            $params[] = $country;
        }
        if ($industry === -1) {
            $where[] = 'b.industry_id IS NULL';
        } elseif ($industry > 0) {
            $where[] = 'b.industry_id = ?';
            $params[] = $industry;
        }
        if ($hasEmail === '1') { $where[] = "b.email_status = 'found'"; }
        if ($completeness > 0) { $where[] = 'b.completeness_score >= ?'; $params[] = $completeness; }
        return ['where' => $where, 'params' => $params, 'country' => $country, 'industry' => $industry, 'has_email' => $hasEmail, 'completeness_min' => $completeness, 'include_raw' => $this->boolean('include_raw')];
    }

    private function buildRows(array $filters): array
    {
        $headers = ['商家名称', '行业', '国家', '城市', '地址', '商家介绍', '电话', '官网', '邮箱', '评分', '评论数', '完整度', 'Google Data ID', '纬度', '经度', '最近更新时间'];
        $sql = "SELECT b.business_name, i.name AS industry_name, COALESCE(c.name_zh, c.name_en) AS country_name, b.city, b.address, b.description,
                    b.phone, b.website,
                    (SELECT GROUP_CONCAT(bc.contact_value SEPARATOR ' | ') FROM business_contacts bc WHERE bc.business_id = b.id AND bc.contact_type = 'email') AS emails,
                    b.rating, b.review_count, b.completeness_score, b.google_data_id, b.latitude, b.longitude, b.updated_at
                FROM businesses b
                LEFT JOIN countries c ON c.id = b.country_id
                LEFT JOIN industries i ON i.id = b.industry_id";
        if ($filters['where']) $sql .= ' WHERE ' . implode(' AND ', $filters['where']);
        $sql .= ' ORDER BY b.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($filters['params']);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                $r['business_name'], $r['industry_name'], $r['country_name'], $r['city'], $r['address'], $r['description'],
                $r['phone'], $r['website'], $r['emails'], $r['rating'], $r['review_count'],
                $r['completeness_score'] . '%', $r['google_data_id'], $r['latitude'], $r['longitude'], $r['updated_at'],
            ];
        }
        return [$headers, $rows];
    }

    private function logExport(string $filename, string $format, int $rows, array $filters): void
    {
        $stmt = db()->prepare('INSERT INTO export_history (user_id, filename, format, row_count, filters_json, created_at) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$this->userId(), $filename, $format, $rows, json_encode($filters, JSON_UNESCAPED_UNICODE), now_utc()]);
        audit('export.create', 'export', $filename, ['format' => $format, 'rows' => $rows]);
    }
}
