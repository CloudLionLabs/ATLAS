<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Support/helpers.php';
require BASE_PATH . '/app/Core/Autoloader.php';
App\Core\Config::load(BASE_PATH . '/config/app.php');

$ok = true;
$line = static function (string $label, bool $pass, string $detail = '') use (&$ok): void {
    if (!$pass) $ok = false;
    echo ($pass ? '[OK]   ' : '[FAIL] ') . $label . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
};

$line('PHP >= 8.2', PHP_VERSION_ID >= 80200, PHP_VERSION);
foreach (['pdo_mysql', 'pdo_sqlite', 'sqlite3', 'mbstring', 'curl', 'openssl', 'zip', 'fileinfo'] as $ext) {
    $line('extension ' . $ext, extension_loaded($ext));
}
$line('.env exists', is_file(BASE_PATH . '/.env'));
$line('storage writable', is_dir(BASE_PATH . '/storage') && is_writable(BASE_PATH . '/storage'), BASE_PATH . '/storage');
foreach (['cache', 'logs', 'raw', 'exports'] as $dir) {
    $path = BASE_PATH . '/storage/' . $dir;
    if (!is_dir($path)) @mkdir($path, 0775, true);
    $line('storage/' . $dir . ' writable', is_dir($path) && is_writable($path), $path);
}
$geo = (string) config('geo_database.path');
$line('geo.sqlite exists', is_file($geo), $geo);
$db = (array) config('database');
try {
    $dsn = 'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['database'] . ';charset=' . $db['charset'];
    $pdo = new PDO($dsn, (string) $db['username'], (string) $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $line('MySQL connect', true, $db['database']);
    $tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    $line('MySQL schema', $tables >= 17, $tables . ' tables');
    $countries = (int) $pdo->query('SELECT COUNT(*) FROM countries')->fetchColumn();
    $line('base data seeded', $countries > 0, $countries . ' countries');
} catch (Throwable $e) {
    $line('MySQL connect', false, $e->getMessage());
}
$line('APP_URL', (string) config('url') !== '', (string) config('url'));
$line('Scraper URL', (string) config('scraper.base_url') !== '', (string) config('scraper.base_url'));
echo PHP_EOL . ($ok ? 'Deployment checks passed.' : 'Deployment checks failed.') . PHP_EOL;
exit($ok ? 0 : 1);
