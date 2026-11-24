<?php
declare(strict_types=1);

namespace App\Modules\Admin\Countries\Controller;

use App\Core\Controller;
use App\Services\GeoCities;
use PDOException;

final class CountryController extends Controller
{
    public function index(): string
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $countryId = (int) ($_GET['country'] ?? 0);
        $uid = $this->userId();
        $params = [$uid];
        $where = '';
        if ($q !== '') {
            $where = 'WHERE c.name_en LIKE ? OR c.name_local LIKE ? OR c.name_zh LIKE ? OR c.iso2 LIKE ?';
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%");
        }
        $sql = "SELECT c.*,
                    (SELECT COUNT(*) FROM regions r WHERE r.country_id = c.id) AS region_count,
                    (SELECT COUNT(*) FROM businesses b WHERE b.country_id = c.id AND b.user_id = ?) AS business_count
                FROM countries c {$where}
                ORDER BY c.is_active DESC, c.name_en ASC";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $countries = $stmt->fetchAll();

        $regions = [];
        if ($countryId > 0) {
            $rs = db()->prepare("SELECT r.*, (SELECT COUNT(*) FROM businesses b WHERE b.region_id = r.id AND b.user_id = ?) AS business_count FROM regions r WHERE r.country_id = ? ORDER BY r.name");
            $rs->execute([$uid, $countryId]);
            $regions = $rs->fetchAll();
        }
        $selected = null;
        foreach ($countries as $c) {
            if ((int) $c['id'] === $countryId) $selected = $c;
        }
        if (!$selected && $countryId > 0) {
            $s = db()->prepare('SELECT * FROM countries WHERE id = ?');
            $s->execute([$countryId]);
            $selected = $s->fetch() ?: null;
        }
        $editing = null;
        if (isset($_GET['edit'])) {
            $s = db()->prepare('SELECT * FROM countries WHERE id = ?');
            $s->execute([(int) $_GET['edit']]);
            $editing = $s->fetch() ?: null;
        }

        return $this->view('Admin/Countries/View/index', [
            'pageTitle' => '国家与地区',
            'active' => 'countries',
            'countries' => $countries,
            'regions' => $regions,
            'selectedCountry' => $selected,
            'selectedCountryId' => $countryId,
            'q' => $q,
            'editing' => $editing,
            'geoCitiesCount' => (new GeoCities())->count(),
        ]);
    }

    public function store(): string
    {
        $this->requireAdmin();
        $iso2 = strtoupper(substr(trim((string) $this->input('iso2')), 0, 2));
        $name = trim((string) $this->input('name_en'));
        if (strlen($iso2) !== 2 || $name === '') {
            $this->flashError('ISO2 国家代码与英文名称必填。');
            $this->redirect('/countries');
        }
        try {
            $stmt = db()->prepare('INSERT INTO countries (iso2, iso3, name_en, name_local, name_zh, default_language, phone_code, center_lat, center_lng, default_zoom, is_active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $iso2,
                strtoupper(substr((string) $this->input('iso3'), 0, 3)),
                $name,
                $this->input('name_local') ?: $name,
                $this->input('name_zh') ?: $name,
                $this->input('default_language', 'en') ?: 'en',
                $this->input('phone_code') ?: null,
                $this->float('center_lat') ?: null,
                $this->float('center_lng') ?: null,
                $this->integer('default_zoom', 5),
                $this->boolean('is_active') ? 1 : 0,
                now_utc(),
                now_utc(),
            ]);
            $id = (int) db()->lastInsertId();
            audit('country.create', 'country', (string) $id, ['iso2' => $iso2]);
            $this->clearOld();
            $this->flashSuccess("国家 {$name} 已接入覆盖网络。");
            $this->redirect('/countries?country=' . $id);
        } catch (PDOException $e) {
            $this->flashError(str_contains($e->getMessage(), 'UNIQUE') ? '该 ISO2 国家代码已存在。' : '保存国家失败。');
            $this->redirect('/countries');
        }
    }

    public function update(array $params): string
    {
        $this->requireAdmin();
        $id = (int) $params['id'];
        $name = trim((string) $this->input('name_en'));
        if ($name === '') {
            $this->flashError('英文名称不能为空。');
            $this->redirect('/countries?edit=' . $id);
        }
        $stmt = db()->prepare('UPDATE countries SET iso3 = ?, name_en = ?, name_local = ?, name_zh = ?, default_language = ?, phone_code = ?, center_lat = ?, center_lng = ?, default_zoom = ?, is_active = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([
            strtoupper(substr((string) $this->input('iso3'), 0, 3)),
            $name,
            $this->input('name_local') ?: $name,
            $this->input('name_zh') ?: $name,
            $this->input('default_language', 'en') ?: 'en',
            $this->input('phone_code') ?: null,
            $this->float('center_lat') ?: null,
            $this->float('center_lng') ?: null,
            $this->integer('default_zoom', 5),
            $this->boolean('is_active') ? 1 : 0,
            now_utc(),
            $id,
        ]);
        audit('country.update', 'country', (string) $id);
        $this->flashSuccess('国家配置已更新。');
        $this->redirect('/countries?country=' . $id);
    }

    public function storeRegion(): string
    {
        $this->requireAdmin();
        $countryId = $this->integer('country_id');
        $name = trim((string) $this->input('name'));
        if (!$countryId || $name === '') {
            $this->flashError('请选择国家并填写地区名称。');
            $this->redirect('/countries');
        }
        $stmt = db()->prepare('INSERT INTO regions (country_id, parent_id, region_type, name, name_local, code, latitude, longitude, default_zoom, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $countryId,
            $this->integer('parent_id') ?: null,
            $this->input('region_type', 'city') ?: 'city',
            $name,
            $this->input('name_local') ?: null,
            $this->input('code') ?: null,
            $this->float('latitude') ?: null,
            $this->float('longitude') ?: null,
            $this->integer('default_zoom', 11),
            $this->boolean('is_active') ? 1 : 0,
            now_utc(),
        ]);
        audit('region.create', 'region', (string) db()->lastInsertId(), ['country_id' => $countryId]);
        $this->flashSuccess("城市节点 {$name} 已加入地图。");
        $this->redirect('/countries?country=' . $countryId);
    }

    public function updateRegion(array $params): string
    {
        $this->requireAdmin();
        $id = (int) $params['id'];
        $s = db()->prepare('SELECT country_id FROM regions WHERE id = ?');
        $s->execute([$id]);
        $countryId = (int) ($s->fetchColumn() ?: 0);
        $stmt = db()->prepare('UPDATE regions SET region_type = ?, name = ?, name_local = ?, code = ?, latitude = ?, longitude = ?, default_zoom = ?, is_active = ? WHERE id = ?');
        $stmt->execute([
            $this->input('region_type', 'city') ?: 'city',
            trim((string) $this->input('name')),
            $this->input('name_local') ?: null,
            $this->input('code') ?: null,
            $this->float('latitude') ?: null,
            $this->float('longitude') ?: null,
            $this->integer('default_zoom', 11),
            $this->boolean('is_active') ? 1 : 0,
            $id,
        ]);
        audit('region.update', 'region', (string) $id);
        $this->flashSuccess('地区节点已更新。');
        $this->redirect('/countries?country=' . $countryId);
    }
}
