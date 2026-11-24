<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Auth;
use App\Services\CsvImporter;

$ownerId = (int) (db()->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
if ($ownerId <= 0) { fwrite(STDERR, "No user available for import\n"); exit(1); }
Auth::forceUser($ownerId);

$jobId = $argv[1] ?? '';
$path = $argv[2] ?? '';
if ($jobId === '' || $path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php scripts/import_csv.php <job-id> <path/to/file.csv>\n");
    exit(1);
}
$stmt = db()->prepare('SELECT * FROM scrape_jobs WHERE id = ? AND user_id = ?');
$stmt->execute([$jobId, $ownerId]);
if (!$stmt->fetch()) {
    fwrite(STDERR, "Job not found: {$jobId}\n");
    exit(1);
}
$result = (new CsvImporter())->importPath($jobId, $path, basename($path));
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
exit(($result['ok'] ?? false) ? 0 : 1);
