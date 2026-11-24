<?php
declare(strict_types=1);

namespace App\Modules\Admin\Keywords\Controller;

use App\Core\Controller;
use PDOException;

final class KeywordController extends Controller
{
    public function index(): string
    {
        $countryId = (int) ($_GET['country'] ?? 0);
        $industryId = (int) ($_GET['industry'] ?? 0);
        $lang = trim((string) ($_GET['lang'] ?? ''));
        $status = $_GET['status'] ?? 'active';
        $q = trim((string) ($_GET['q'] ?? ''));

        $uid = $this->userId();
        $where = ['k.user_id = ?'];
        $params = [$uid];
        if ($countryId) { $where[] = 'k.country_id = ?'; $params[] = $countryId; }
        if ($industryId) { $where[] = 'k.industry_id = ?'; $params[] = $industryId; }
        if ($lang !== '') { $where[] = 'k.language_code = ?'; $params[] = $lang; }
        if ($status === 'active') { $where[] = 'k.is_active = 1'; }
        if ($status === 'inactive') { $where[] = 'k.is_active = 0'; }
        if ($q !== '') { $where[] = 'k.keyword LIKE ?'; $params[] = "%{$q}%"; }
        $sql = "SELECT k.*, c.iso2, c.name_en AS country_name, i.name AS industry_name
                FROM keywords k
                LEFT JOIN countries c ON c.id = k.country_id
                LEFT JOIN industries i ON i.id = k.industry_id
                " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
                ORDER BY k.is_active DESC, k.created_at DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $keywords = $stmt->fetchAll();

        $countries = db()->query('SELECT * FROM countries WHERE is_active = 1 ORDER BY name_en')->fetchAll();
        $industries = db()->query('SELECT * FROM industries WHERE is_active = 1 ORDER BY name')->fetchAll();
        $languagesStmt = db()->prepare('SELECT DISTINCT language_code FROM keywords WHERE user_id = ? ORDER BY language_code');
        $languagesStmt->execute([$uid]);
        $languages = $languagesStmt->fetchAll();

        return $this->view('Admin/Keywords/View/index', [
            'pageTitle' => 'B2B 关键词库',
            'active' => 'keywords',
            'keywords' => $keywords,
            'countries' => $countries,
            'industries' => $industries,
            'languages' => $languages,
            'filters' => compact('countryId', 'industryId', 'lang', 'status', 'q'),
            'activeCount' => (int) (static function () use ($uid): int { $s = db()->prepare('SELECT COUNT(*) FROM keywords WHERE user_id = ? AND is_active = 1'); $s->execute([$uid]); return (int) $s->fetchColumn(); })(),
        ]);
    }

    public function store(): string
    {
        $keyword = trim((string) $this->input('keyword'));
        $industryId = $this->integer('industry_id') ?: null;
        $countryId = $this->integer('country_id') ?: null;
        $lang = preg_replace('/[^a-zA-Z-]/', '', (string) $this->input('language_code', 'en')) ?: 'en';
        if (mb_strlen($keyword) < 2) {
            $this->flashError('关键词至少需要 2 个字符。');
            $this->redirect('/keywords');
        }
        if ($this->duplicateExists($keyword, $industryId, $countryId, $lang)) {
            $this->flashError('已存在相同的国家 / 行业 / 语言 / 关键词组合。');
            $this->redirect('/keywords');
        }
        $stmt = db()->prepare('INSERT INTO keywords (user_id, industry_id, country_id, language_code, keyword, is_active, notes, created_at) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$this->userId(), $industryId, $countryId, $lang, $keyword, $this->boolean('is_active') ? 1 : 0, $this->input('notes') ?: null, now_utc()]);
        audit('keyword.create', 'keyword', (string) db()->lastInsertId(), ['keyword' => $keyword]);
        $this->flashSuccess('关键词已加入词库。');
        $this->redirect('/keywords');
    }

    public function bulk(): string
    {
        $raw = trim((string) $this->input('keywords', ''));
        $industryId = $this->integer('industry_id') ?: null;
        $countryId = $this->integer('country_id') ?: null;
        $lang = preg_replace('/[^a-zA-Z-]/', '', (string) $this->input('language_code', 'en')) ?: 'en';
        if ($raw === '') {
            $this->flashError('请粘贴至少一个关键词。');
            $this->redirect('/keywords');
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $added = 0;
        $skipped = 0;
        $stmt = db()->prepare('INSERT INTO keywords (user_id, industry_id, country_id, language_code, keyword, is_active, notes, created_at) VALUES (?,?,?,?,?,1,?,?)');
        foreach ($lines as $line) {
            $keyword = trim($line);
            if ($keyword === '') continue;
            if (mb_strlen($keyword) < 2 || mb_strlen($keyword) > 300) { $skipped++; continue; }
            if ($this->duplicateExists($keyword, $industryId, $countryId, $lang)) { $skipped++; continue; }
            try {
                $stmt->execute([$this->userId(), $industryId, $countryId, $lang, $keyword, '批量导入', now_utc()]);
                $added++;
            } catch (PDOException) {
                $skipped++;
            }
        }
        audit('keyword.bulk', 'keyword', null, ['added' => $added, 'skipped' => $skipped]);
        $this->flashSuccess("批量导入完成：新增 {$added} 条，跳过 {$skipped} 条重复或无效记录。");
        $this->redirect('/keywords');
    }

    public function update(array $params): string
    {
        $id = (int) $params['id'];
        $stmt = db()->prepare('UPDATE keywords SET industry_id = ?, country_id = ?, language_code = ?, keyword = ?, is_active = ?, notes = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([
            $this->integer('industry_id') ?: null,
            $this->integer('country_id') ?: null,
            preg_replace('/[^a-zA-Z-]/', '', (string) $this->input('language_code', 'en')) ?: 'en',
            trim((string) $this->input('keyword')),
            $this->boolean('is_active') ? 1 : 0,
            $this->input('notes') ?: null,
            $id,
            $this->userId(),
        ]);
        audit('keyword.update', 'keyword', (string) $id);
        $this->flashSuccess('关键词已更新。');
        $this->redirect('/keywords');
    }

    public function toggle(array $params): string
    {
        $id = (int) $params['id'];
        db()->prepare('UPDATE keywords SET is_active = CASE is_active WHEN 1 THEN 0 ELSE 1 END WHERE id = ? AND user_id = ?')->execute([$id, $this->userId()]);
        $this->flashSuccess('关键词状态已切换。');
        $this->redirect('/keywords');
    }

    private function duplicateExists(string $keyword, ?int $industryId, ?int $countryId, string $lang): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM keywords WHERE user_id = ? AND lower(keyword) = lower(?) AND COALESCE(industry_id,0) = ? AND COALESCE(country_id,0) = ? AND language_code = ?');
        $stmt->execute([$this->userId(), $keyword, (int) $industryId, (int) $countryId, $lang]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
