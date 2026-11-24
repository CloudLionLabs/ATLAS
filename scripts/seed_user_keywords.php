<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Services\KeywordSeeder;

$arg = $argv[1] ?? '--all-empty';
$seeder = new KeywordSeeder();
$pdo = db();

if ($arg === '--all-empty') {
    $users = $pdo->query('SELECT id, username FROM users WHERE is_active = 1 ORDER BY id')->fetchAll();
    $seeded = 0;
    foreach ($users as $user) {
        $id = (int) $user['id'];
        $count = (int) $pdo->query('SELECT COUNT(*) FROM keywords WHERE user_id = ' . $id)->fetchColumn();
        if ($count > 0) {
            echo "Skip {$user['username']} (already has {$count} keywords)\n";
            continue;
        }
        $added = $seeder->seedForUser($id);
        echo "Seeded {$user['username']}: {$added} keywords\n";
        $seeded++;
    }
    echo "Seeded {$seeded} tenant(s).\n";
    exit(0);
}

$userId = (int) $arg;
if ($userId <= 0) {
    fwrite(STDERR, "Usage: php scripts/seed_user_keywords.php <user_id> | --all-empty\n");
    exit(1);
}
$added = $seeder->seedForUser($userId);
echo "Seeded user {$userId}: {$added} keywords\n";
