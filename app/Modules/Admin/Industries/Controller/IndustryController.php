<?php
declare(strict_types=1);

namespace App\Modules\Admin\Industries\Controller;

use App\Core\Controller;
use PDOException;

final class IndustryController extends Controller
{
    public function index(): string
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $uid = $this->userId();
        $params = [$uid, $uid];
        $whereParts = ['i.is_active = 1'];
        if ($q !== '') {
            $whereParts[] = '(i.name LIKE ? OR i.code LIKE ?)';
            array_push($params, "%{$q}%", "%{$q}%");
        }
        $where = 'WHERE ' . implode(' AND ', $whereParts);
        $stmt = db()->prepare("SELECT i.*,
                (SELECT COUNT(*) FROM businesses b WHERE b.industry_id = i.id AND b.user_id = ?) AS business_count,
                (SELECT COUNT(*) FROM keywords k WHERE k.industry_id = i.id AND k.user_id = ?) AS keyword_count,
                (SELECT COUNT(*) FROM industry_translations t WHERE t.industry_id = i.id) AS translation_count
            FROM industries i {$where}
            ORDER BY i.sort_order, i.name");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $tree = $this->buildTree($rows);

        $translations = db()->query('SELECT t.*, i.name AS industry_name FROM industry_translations t JOIN industries i ON i.id = t.industry_id WHERE i.is_active = 1 ORDER BY i.name, t.language_code')->fetchAll();
        $editing = null;
        if (isset($_GET['edit'])) {
            $s = db()->prepare('SELECT * FROM industries WHERE id = ?');
            $s->execute([(int) $_GET['edit']]);
            $editing = $s->fetch() ?: null;
        }
        return $this->view('Admin/Industries/View/index', [
            'pageTitle' => 'B2B 行业',
            'active' => 'industries',
            'industries' => $rows,
            'tree' => $tree,
            'translations' => $translations,
            'editing' => $editing,
            'q' => $q,
            'totalBusinesses' => array_sum(array_map(static fn ($r) => (int) $r['business_count'], $rows)),
            'totalKeywords' => array_sum(array_map(static fn ($r) => (int) $r['keyword_count'], $rows)),
        ]);
    }

    public function store(): string
    {
        $this->requireAdmin();
        $code = strtolower(trim((string) $this->input('code')));
        $name = trim((string) $this->input('name'));
        if ($code === '' || $name === '') {
            $this->flashError('行业编码与名称必填。');
            $this->redirect('/industries');
        }
        $code = preg_replace('/[^a-z0-9-]+/', '-', $code) ?: '';
        try {
            $stmt = db()->prepare('INSERT INTO industries (parent_id, code, name, description, is_active, sort_order, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $this->integer('parent_id') ?: null,
                $code,
                $name,
                $this->input('description') ?: null,
                $this->boolean('is_active') ? 1 : 0,
                $this->integer('sort_order', 100),
                now_utc(),
                now_utc(),
            ]);
            $id = (int) db()->lastInsertId();
            $this->saveTranslation($id, (string) $this->input('name_zh', ''), 'zh');
            audit('industry.create', 'industry', (string) $id, ['code' => $code]);
            $this->flashSuccess("行业节点 {$name} 已创建。");
        } catch (PDOException $e) {
            $this->flashError(str_contains($e->getMessage(), 'UNIQUE') ? '行业编码已存在。' : '保存行业失败。');
        }
        $this->redirect('/industries');
    }

    public function update(array $params): string
    {
        $this->requireAdmin();
        $id = (int) $params['id'];
        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flashError('行业名称不能为空。');
            $this->redirect('/industries?edit=' . $id);
        }
        if ((int) $this->input('parent_id') === $id) {
            $this->flashError('行业不能把自身设为父级。');
            $this->redirect('/industries?edit=' . $id);
        }
        $stmt = db()->prepare('UPDATE industries SET parent_id = ?, name = ?, description = ?, is_active = ?, sort_order = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([
            $this->integer('parent_id') ?: null,
            $name,
            $this->input('description') ?: null,
            $this->boolean('is_active') ? 1 : 0,
            $this->integer('sort_order', 100),
            now_utc(),
            $id,
        ]);
        $this->saveTranslation($id, (string) $this->input('name_zh', ''), 'zh');
        audit('industry.update', 'industry', (string) $id);
        $this->flashSuccess('行业配置已更新。');
        $this->redirect('/industries');
    }

    public function translation(array $params): string
    {
        $this->requireAdmin();
        $id = (int) $params['id'];
        $lang = preg_replace('/[^a-zA-Z-]/', '', (string) $this->input('language_code', 'zh')) ?: 'zh';
        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flashError('翻译名称不能为空。');
            $this->redirect('/industries');
        }
        $this->saveTranslation($id, $name, $lang);
        $this->flashSuccess('多语言名称已保存。');
        $this->redirect('/industries');
    }

    private function saveTranslation(int $industryId, string $name, string $lang): void
    {
        $name = trim($name);
        if ($name === '') return;
        $stmt = db()->prepare('INSERT INTO industry_translations (industry_id, language_code, name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
        $stmt->execute([$industryId, $lang, $name]);
    }

    private function buildTree(array $rows, ?int $parentId = null, int $depth = 0): array
    {
        $branch = [];
        foreach ($rows as $row) {
            $pid = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            if ($pid === $parentId) {
                $row['depth'] = $depth;
                $row['children'] = $this->buildTree($rows, (int) $row['id'], $depth + 1);
                $branch[] = $row;
            }
        }
        return $branch;
    }
}
