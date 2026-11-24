<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\GeoDatabase;
use Throwable;

final class GeoCities
{
    public function count(): int
    {
        $pdo = GeoDatabase::connection();
        if (!$pdo) return 0;
        try {
            return (int) $pdo->query('SELECT COUNT(*) FROM geo_cities')->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    public function find(int $id): ?array
    {
        $pdo = GeoDatabase::connection();
        if (!$pdo) return null;
        try {
            $stmt = $pdo->prepare('SELECT id, name, ascii_name, name_zh, country_code, admin1, admin1_name, latitude, longitude, population, timezone FROM geo_cities WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $stmt->closeCursor();
            if (!$row) return null;
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'name_zh' => (string) ($row['name_zh'] ?? ''),
                'country_code' => (string) $row['country_code'],
                'admin1_name' => (string) ($row['admin1_name'] ?? ''),
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
                'population' => (int) $row['population'],
                'timezone' => (string) ($row['timezone'] ?? ''),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Search across city name, ASCII name and multilingual alternate names.
     * Returns at most $limit rows ordered by exact-name then population.
     */
    public function search(string $query, string $countryCode = '', int $limit = 20): array
    {
        $pdo = GeoDatabase::connection();
        if (!$pdo) {
            return ['available' => false, 'cities' => [], 'total' => 0];
        }
        $query = trim($query);
        $countryCode = strtoupper(trim($countryCode));
        $limit = max(1, min(5000, $limit));
        $where = [];
        $params = [];
        if ($countryCode !== '') {
            $where[] = 'country_code = ?';
            $params[] = $countryCode;
        }
        if ($query !== '') {
            $like = '%' . $this->escapeLike($query) . '%';
            $where[] = "(name LIKE ? ESCAPE '\\' OR ascii_name LIKE ? ESCAPE '\\' OR alternate_names LIKE ? ESCAPE '\\')";
            array_push($params, $like, $like, $like);
        }
        $sql = 'SELECT id, name, ascii_name, name_zh, country_code, admin1, admin1_name, latitude, longitude, population, timezone
                FROM geo_cities';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        if ($query !== '') {
            $sql .= ' ORDER BY CASE WHEN lower(name) = lower(?) THEN 0 WHEN lower(ascii_name) = lower(?) THEN 1 ELSE 2 END, population DESC';
            $params[] = $query;
            $params[] = $query;
        } else {
            $sql .= ' ORDER BY population DESC';
        }
        $sql .= ' LIMIT ?';
        $params[] = $limit;

        $stmt = $pdo->prepare($sql);
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        $cities = [];
        foreach ($stmt->fetchAll() as $row) {
            $cities[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'name_zh' => (string) ($row['name_zh'] ?? ''),
                'ascii_name' => (string) $row['ascii_name'],
                'country_code' => (string) $row['country_code'],
                'admin1' => (string) ($row['admin1'] ?? ''),
                'admin1_name' => (string) ($row['admin1_name'] ?? ($row['admin1'] ?? '')),
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
                'population' => (int) $row['population'],
                'timezone' => (string) ($row['timezone'] ?? ''),
            ];
        }
        $stmt->closeCursor();
        return ['available' => true, 'cities' => $cities, 'total' => $this->count()];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
