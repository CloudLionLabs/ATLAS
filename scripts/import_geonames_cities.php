<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$zipPath = $argv[1] ?? base_path('storage/geo/cities15000.zip');
$geoPath = (string) config('geo_database.path');
$geoDir = dirname($geoPath);
if (!is_dir($geoDir) && !mkdir($geoDir, 0775, true) && !is_dir($geoDir)) {
    fwrite(STDERR, "Cannot create storage directory: {$geoDir}\n");
    exit(1);
}
$downloadDir = base_path('storage/geo');
if (!is_dir($downloadDir) && !mkdir($downloadDir, 0775, true) && !is_dir($downloadDir)) {
    fwrite(STDERR, "Cannot create download directory: {$downloadDir}\n");
    exit(1);
}

if (!is_file($zipPath)) {
    echo "Downloading GeoNames cities15000.zip...\n";
    $ch = curl_init('https://download.geonames.org/export/dump/cities15000.zip');
    $fh = fopen($zipPath, 'wb');
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_USERAGENT => 'AtlasGlobalBusinessAdmin/1.0 city-import',
    ]);
    $ok = curl_exec($ch);
    $error = curl_error($ch);
    fclose($fh);
    if (!$ok || $error !== '') {
        @unlink($zipPath);
        fwrite(STDERR, 'Download failed: ' . $error . "\n");
        exit(1);
    }
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    fwrite(STDERR, "Cannot open zip: {$zipPath}\n");
    exit(1);
}
$entry = $zip->getFromName('cities15000.txt');
$zip->close();
if ($entry === false) {
    fwrite(STDERR, "cities15000.txt not found in archive\n");
    exit(1);
}
$txtPath = $downloadDir . '/cities15000.txt';
file_put_contents($txtPath, $entry);
unset($entry);

$admin1Path = $downloadDir . '/admin1CodesASCII.txt';
if (!is_file($admin1Path)) {
    echo "Downloading GeoNames admin1CodesASCII.txt...\n";
    $admin1Raw = @file_get_contents('https://download.geonames.org/export/dump/admin1CodesASCII.txt');
    if ($admin1Raw === false) {
        fwrite(STDERR, "Cannot download admin1CodesASCII.txt\n");
        exit(1);
    }
    file_put_contents($admin1Path, $admin1Raw);
}
$admin1Map = [];
foreach (file($admin1Path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $parts = explode("\t", $line);
    if (count($parts) >= 2) $admin1Map[$parts[0]] = $parts[1];
}

$tmpPath = $geoPath . '.tmp';
foreach ([$tmpPath, $tmpPath . '-wal', $tmpPath . '-shm'] as $file) {
    if (is_file($file)) unlink($file);
}
$pdo = new PDO('sqlite:' . $tmpPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA journal_mode = DELETE');
$pdo->exec('PRAGMA synchronous = OFF');
$pdo->exec('PRAGMA temp_store = MEMORY');
$pdo->exec('CREATE TABLE geo_cities (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    ascii_name TEXT,
    alternate_names TEXT,
    name_zh TEXT,
    country_code TEXT NOT NULL,
    admin1 TEXT,
    admin1_name TEXT,
    latitude REAL NOT NULL,
    longitude REAL NOT NULL,
    population INTEGER NOT NULL DEFAULT 0,
    timezone TEXT
)');
$pdo->exec('CREATE TABLE geo_meta (key TEXT PRIMARY KEY, value TEXT)');
$pdo->beginTransaction();
$insert = $pdo->prepare('INSERT INTO geo_cities (id, name, ascii_name, alternate_names, name_zh, country_code, admin1, admin1_name, latitude, longitude, population, timezone) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
$zhNames = is_file(base_path('database/city_names_zh.php')) ? require base_path('database/city_names_zh.php') : [];
$fh = fopen($txtPath, 'rb');
$count = 0;
$skipped = 0;
while (($row = fgetcsv($fh, 0, "\t", '"', '')) !== false) {
    if (count($row) < 18) { $skipped++; continue; }
    $id = (int) $row[0];
    $name = trim((string) $row[1]);
    $ascii = trim((string) $row[2]);
    $alternates = trim((string) $row[3]);
    $lat = (float) $row[4];
    $lng = (float) $row[5];
    $country = strtoupper(trim((string) $row[8]));
    $admin1 = trim((string) $row[10]);
    $admin1Name = $admin1Map[$country . '.' . $admin1] ?? $admin1;
    $population = (int) ($row[14] ?? 0);
    $timezone = trim((string) ($row[17] ?? ''));
    if ($id <= 0 || $name === '' || strlen($country) !== 2 || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        $skipped++;
        continue;
    }
    // Keep alternate names searchable (they often include Chinese/local names)
    // but cap pathological rows to keep the dedicated database reasonably sized.
    if (strlen($alternates) > 20000) $alternates = substr($alternates, 0, 20000);
    // Use only names tagged with a Chinese language code. Searching the raw
    // alternate list would sometimes select a Japanese Kanji alias.
    $nameZh = $zhNames[$id] ?? null;
    $insert->execute([$id, $name, $ascii, $alternates, $nameZh, $country, $admin1, $admin1Name, $lat, $lng, $population, $timezone]);
    $count++;
}
fclose($fh);
$pdo->exec('CREATE INDEX idx_geo_cities_country_population ON geo_cities(country_code, population DESC)');
$pdo->exec('CREATE INDEX idx_geo_cities_country_name ON geo_cities(country_code, name COLLATE NOCASE)');
$meta = $pdo->prepare('INSERT INTO geo_meta (key, value) VALUES (?,?)');
$meta->execute(['source', 'GeoNames cities15000']);
$meta->execute(['source_url', 'https://download.geonames.org/export/dump/cities15000.zip']);
$meta->execute(['city_count', (string) $count]);
$meta->execute(['imported_at', now_utc()]);
$pdo->commit();
$pdo->exec('PRAGMA optimize');
$pdo = null;

foreach ([$geoPath, $geoPath . '-wal', $geoPath . '-shm'] as $file) {
    if (is_file($file)) unlink($file);
}
if (!rename($tmpPath, $geoPath)) {
    fwrite(STDERR, "Cannot move temporary city database into place\n");
    exit(1);
}
@chmod($geoPath, 0664);
echo "Imported {$count} cities (skipped {$skipped}) into {$geoPath}\n";
echo "Source text retained at {$txtPath}\n";
