<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Services\CsvImporter;

$pdo = db();
$stmt = $pdo->query("SELECT b.id, rr.raw_json
    FROM businesses b
    JOIN business_sources bs ON bs.business_id = b.id
    JOIN raw_records rr ON rr.id = bs.raw_record_id
    WHERE (b.description IS NULL OR b.description = '')
    ORDER BY bs.last_seen_at DESC");
$updated = 0;
$seen = [];
$update = $pdo->prepare('UPDATE businesses SET description = ?, updated_at = ? WHERE id = ?');
foreach ($stmt->fetchAll() as $row) {
    $id = (int) $row['id'];
    if (isset($seen[$id])) continue;
    $seen[$id] = true;
    $raw = json_decode((string) $row['raw_json'], true);
    if (!is_array($raw)) continue;
    $description = CsvImporter::descriptionFromFields($raw);
    if ($description !== null) {
        $update->execute([$description, now_utc(), $id]);
        $updated++;
    }
}
echo "Backfilled {$updated} business descriptions from raw records.\n";
