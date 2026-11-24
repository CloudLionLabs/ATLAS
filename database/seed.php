<?php
declare(strict_types=1);

$countries = require __DIR__ . '/countries_global.php';

$regions = [
    ['GB', 'London', 'London', 'city', 51.5074, -0.1278, 12],
    ['GB', 'Manchester', 'Manchester', 'city', 53.4808, -2.2426, 12],
    ['GB', 'Birmingham', 'Birmingham', 'city', 52.4862, -1.8904, 12],
    ['US', 'New York', 'New York', 'city', 40.7128, -74.0060, 11],
    ['US', 'Los Angeles', 'Los Angeles', 'city', 34.0522, -118.2437, 11],
    ['US', 'Miami', 'Miami', 'city', 25.7617, -80.1918, 12],
    ['CA', 'Toronto', 'Toronto', 'city', 43.6532, -79.3832, 11],
    ['CA', 'Vancouver', 'Vancouver', 'city', 49.2827, -123.1207, 11],
    ['AU', 'Sydney', 'Sydney', 'city', -33.8688, 151.2093, 11],
    ['AU', 'Melbourne', 'Melbourne', 'city', -37.8136, 144.9631, 11],
    ['DE', 'Berlin', 'Berlin', 'city', 52.5200, 13.4050, 11],
    ['DE', 'Munich', 'München', 'city', 48.1351, 11.5820, 11],
    ['FR', 'Paris', 'Paris', 'city', 48.8566, 2.3522, 11],
    ['FR', 'Lyon', 'Lyon', 'city', 45.7640, 4.8357, 12],
    ['JP', 'Tokyo', '東京', 'city', 35.6762, 139.6503, 11],
    ['JP', 'Osaka', '大阪', 'city', 34.6937, 135.5023, 11],
    ['SG', 'Singapore', 'Singapore', 'city', 1.3521, 103.8198, 11],
    ['AE', 'Dubai', 'دبي', 'city', 25.2048, 55.2708, 11],
    ['AE', 'Abu Dhabi', 'أبو ظبي', 'city', 24.4539, 54.3773, 11],
    ['BR', 'São Paulo', 'São Paulo', 'city', -23.5505, -46.6333, 11],
    ['IN', 'Mumbai', 'मुम्बई', 'city', 19.0760, 72.8777, 11],
    ['NL', 'Amsterdam', 'Amsterdam', 'city', 52.3676, 4.9041, 11],
    ['ES', 'Madrid', 'Madrid', 'city', 40.4168, -3.7038, 11],
    ['IT', 'Rome', 'Roma', 'city', 41.9028, 12.4964, 11],
    ['NZ', 'Auckland', 'Auckland', 'city', -36.8509, 174.7645, 11],
    ['ZA', 'Cape Town', 'Cape Town', 'city', -33.9249, 18.4241, 11],
];

$b2b = require __DIR__ . '/b2b_taxonomy.php';
$industries = array_map(static fn (array $row): array => [$row[0], $row[1], $row[3], $row[4], $row[5]], $b2b['industries']);
$translations = array_map(static fn (array $row): array => [$row[0], 'zh', $row[2]], $b2b['industries']);
$keywords = array_map(static fn (array $row): array => [$row[0], $row[1], $row[2], $row[3], $row[4]], $b2b['keywords']);

$pdo->beginTransaction();
try {
    $adminId = (int) ($pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($adminId === 0) {
        $adminUser = (string) env('INITIAL_ADMIN_USERNAME', 'admin');
        $adminPass = (string) env('INITIAL_ADMIN_PASSWORD', 'Atlas!2026');
        $adminName = (string) env('INITIAL_ADMIN_NAME', '管理员');
        $u = $pdo->prepare('INSERT INTO users (username, display_name, password_hash, role, is_active, created_at, updated_at) VALUES (?,?,?,?,1,?,?)');
        $u->execute([$adminUser, $adminName, password_hash($adminPass, PASSWORD_DEFAULT), 'admin', now_utc(), now_utc()]);
        $adminId = (int) $pdo->lastInsertId();
    }

    $countryIds = [];
    $stmt = $pdo->prepare('INSERT IGNORE INTO countries (iso2, iso3, name_en, name_local, name_zh, default_language, phone_code, center_lat, center_lng, default_zoom, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,1)');
    foreach ($countries as $c) {
        $stmt->execute($c);
    }
    foreach ($pdo->query('SELECT id, iso2 FROM countries') as $row) {
        $countryIds[$row['iso2']] = (int) $row['id'];
    }

    $stmt = $pdo->prepare('INSERT INTO regions (country_id, region_type, name, name_local, latitude, longitude, default_zoom, is_active) VALUES (?,?,?,?,?,?,?,1)');
    foreach ($regions as [$iso2, $name, $local, $type, $lat, $lng, $zoom]) {
        if (!isset($countryIds[$iso2])) continue;
        $stmt->execute([$countryIds[$iso2], $type, $name, $local, $lat, $lng, $zoom]);
    }

    $industryIds = [];
    $stmt = $pdo->prepare('INSERT IGNORE INTO industries (code, name, description, sort_order, is_active) VALUES (?,?,?,?,1)');
    foreach ($industries as [$code, $name, $desc, $parent, $sort]) {
        $stmt->execute([$code, $name, $desc, $sort]);
    }
    foreach ($pdo->query('SELECT id, code FROM industries') as $row) {
        $industryIds[$row['code']] = (int) $row['id'];
    }
    $upd = $pdo->prepare('UPDATE industries SET parent_id = ? WHERE code = ? AND parent_id IS NULL');
    foreach ($industries as [$code, , , $parent]) {
        if ($parent && isset($industryIds[$parent])) {
            $upd->execute([$industryIds[$parent], $code]);
        }
    }
    $tr = $pdo->prepare('INSERT IGNORE INTO industry_translations (industry_id, language_code, name) VALUES (?,?,?)');
    foreach ($translations as [$code, $lang, $name]) {
        if (isset($industryIds[$code])) $tr->execute([$industryIds[$code], $lang, $name]);
    }
    $kw = $pdo->prepare('INSERT INTO keywords (user_id, industry_id, country_id, language_code, keyword, notes, is_active) VALUES (?,?,?,?,?,?,1)');
    foreach ($keywords as [$industry, $iso2, $lang, $keyword, $notes]) {
        if (!isset($industryIds[$industry], $countryIds[$iso2])) continue;
        $kw->execute([$adminId, $industryIds[$industry], $countryIds[$iso2], $lang, $keyword, $notes]);
    }

    $settings = $pdo->prepare('INSERT IGNORE INTO app_settings (`key`, value) VALUES (?,?)');
    foreach ([
        ['email_finder_enabled', '1'],
        ['email_finder_max_pages', '3'],
        ['email_domain_blacklist', ''],
        ['default_language', 'en'],
        ['default_radius', '10000'],
        ['default_depth', '10'],
        ['data_retention_days', '365'],
    ] as $s) {
        $settings->execute($s);
        $us = $pdo->prepare('INSERT IGNORE INTO user_settings (user_id, `key`, value, updated_at) VALUES (?,?,?,?)');
        $us->execute([$adminId, $s[0], $s[1], now_utc()]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
