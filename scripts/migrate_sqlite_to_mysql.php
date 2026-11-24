<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Support/helpers.php';
require BASE_PATH . '/app/Core/Autoloader.php';
App\Core\Config::load(BASE_PATH . '/config/app.php');

$sourcePath = $argv[1] ?? null;
if ($sourcePath === null) {
    $sourcePath = base_path('storage/app.sqlite');
    if (!is_file($sourcePath)) {
        $candidates = glob(base_path('storage/backups/app-before-mysql-*.sqlite')) ?: [];
        rsort($candidates);
        $sourcePath = $candidates[0] ?? $sourcePath;
    }
}
if (!is_file($sourcePath)) {
    fwrite(STDERR, "SQLite source not found: {$sourcePath}\n");
    exit(1);
}
$db = (array) config('database');
$dsn = 'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['database'] . ';charset=' . $db['charset'];
$target = new PDO($dsn, (string) $db['username'], (string) $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$source = new PDO('sqlite:' . $sourcePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$hasUsersTable = (bool) $target->query("SHOW TABLES LIKE 'users'")->fetchColumn();
if (!$hasUsersTable) {
    $schemaFile = base_path('database/schema_mysql.sql');
    if (!is_file($schemaFile)) {
        fwrite(STDERR, "Missing schema file: database/schema_mysql.sql\n");
        exit(1);
    }
    foreach (preg_split('/;\s*(?:\r?\n|$)/', (string) file_get_contents($schemaFile)) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $target->exec($statement);
    }
}

$tables = [
    'users', 'countries', 'regions', 'industries', 'industry_translations',
    'keywords', 'scrape_batches', 'scrape_jobs', 'businesses', 'business_contacts',
    'raw_records', 'business_sources', 'import_logs', 'audit_logs',
    'export_history', 'app_settings', 'user_settings',
];

$sourceColumns = static function (PDO $pdo, string $table): array {
    $cols = [];
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')') as $row) {
        $cols[(string) $row['name']] = true;
    }
    return $cols;
};
$targetMeta = static function (PDO $pdo, string $table): array {
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`') as $row) {
        $cols[(string) $row['Field']] = [
            'type' => strtolower((string) $row['Type']),
            'null' => strtoupper((string) $row['Null']) === 'YES',
        ];
    }
    return $cols;
};
$validDateTime = static function (mixed $value): bool {
    $value = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1
        && strtotime($value) !== false;
};

$target->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (array_reverse($tables) as $table) {
    $target->exec('TRUNCATE TABLE `' . $table . '`');
}
$target->exec('SET FOREIGN_KEY_CHECKS = 1');
$validUserIds = array_map('intval', $target->query('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN));
$target->beginTransaction();
$total = 0;
foreach ($tables as $table) {
    $srcCols = $sourceColumns($source, $table);
    $dstMeta = $targetMeta($target, $table);
    $columns = [];
    foreach ($dstMeta as $column => $meta) {
        if (isset($srcCols[$column])) {
            $columns[$column] = $column;
        } elseif ($column === 'row_no' && isset($srcCols['row_number'])) {
            $columns[$column] = 'row_number';
        } else {
            fwrite(STDERR, "Missing source column {$table}.{$column}\n");
            exit(1);
        }
    }
    $select = implode(', ', array_map(static fn ($c) => '`' . $c . '`', array_values($columns)));
    $insertCols = implode(', ', array_map(static fn ($c) => '`' . $c . '`', array_keys($columns)));
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $insert = $target->prepare("INSERT INTO `{$table}` ({$insertCols}) VALUES ({$placeholders})");
    $rows = $source->query("SELECT {$select} FROM `{$table}`");
    $count = 0;
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        $values = [];
        foreach ($columns as $targetColumn => $sourceColumn) {
            $value = $row[$sourceColumn] ?? null;
            $type = $dstMeta[$targetColumn]['type'] ?? '';
            if (str_starts_with($type, 'datetime') || str_starts_with($type, 'date') || str_starts_with($type, 'timestamp')) {
                if ($value === null || $value === '' || !$validDateTime($value)) {
                    if (!$dstMeta[$targetColumn]['null'] && in_array($targetColumn, ['created_at', 'updated_at', 'discovered_at', 'first_seen_at', 'last_seen_at'], true)) {
                        $value = gmdate('Y-m-d H:i:s');
                    } else {
                        $value = null;
                    }
                }
            } elseif (preg_match('/^(decimal|double|float|int|bigint|smallint|tinyint)/', $type) === 1) {
                if ($value === null || $value === '' || !is_numeric($value)) {
                    $value = $dstMeta[$targetColumn]['null'] ? null : 0;
                }
            }
            $values[] = $value;
        }
        if ($table === 'audit_logs') {
            $userIndex = array_search('user_id', array_keys($columns), true);
            if ($userIndex !== false) {
                $uidValue = $values[$userIndex];
                if ($uidValue !== null && !in_array((int) $uidValue, $validUserIds, true)) {
                    $values[$userIndex] = null;
                }
            }
        }
        $insert->execute($values);
        $count++;
    }
    echo "{$table}: {$count}\n";
    $total += $count;
}
$target->commit();
echo "Migrated {$total} rows from SQLite to MySQL ({$db['database']}).\n";
foreach ($tables as $table) {
    $sourceCount = (int) $source->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    $targetCount = (int) $target->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    if ($sourceCount !== $targetCount) {
        fwrite(STDERR, "Count mismatch {$table}: source {$sourceCount}, target {$targetCount}\n");
        exit(1);
    }
}
echo "All table row counts match.\n";
