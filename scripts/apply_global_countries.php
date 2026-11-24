<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$countries = require BASE_PATH . '/database/countries_global.php';
$pdo = db();

// Bring existing databases up to the current schema before upserting.
$cols = array_column($pdo->query('SHOW COLUMNS FROM countries')->fetchAll(), 'Field');
if (!in_array('name_zh', $cols, true)) {
    $pdo->exec('ALTER TABLE countries ADD COLUMN name_zh VARCHAR(255) NULL');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO countries (iso2, iso3, name_en, name_local, name_zh, default_language, phone_code, center_lat, center_lng, default_zoom, is_active, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,1,?,?)
        ON DUPLICATE KEY UPDATE
            iso3 = VALUES(iso3),
            name_en = VALUES(name_en),
            name_local = VALUES(name_local),
            name_zh = VALUES(name_zh),
            default_language = VALUES(default_language),
            phone_code = VALUES(phone_code),
            center_lat = VALUES(center_lat),
            center_lng = VALUES(center_lng),
            default_zoom = VALUES(default_zoom),
            updated_at = VALUES(updated_at)');
    foreach ($countries as $row) {
        [$iso2, $iso3, $nameEn, $nameLocal, $nameZh, $lang, $phone, $lat, $lng, $zoom] = $row;
        $stmt->execute([$iso2, $iso3, $nameEn, $nameLocal, $nameZh, $lang, $phone, $lat, $lng, $zoom, now_utc(), now_utc()]);
    }
    audit('countries.global.apply', 'country', null, ['countries' => count($countries)]);
    $pdo->commit();
    echo 'Global country registry applied: ' . count($countries) . " countries.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
