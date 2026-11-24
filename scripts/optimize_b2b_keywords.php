<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Auth;

$pdo = db();
$adminId = (int) ($pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
if ($adminId > 0) Auth::forceUser($adminId);

// Only exact seeded terms are touched; custom tenant keywords are left alone.
$map = [
    'industrial machinery importers in Berlin' => 'industrial machinery importers',
    'construction equipment distributors in Dubai' => 'construction equipment distributors',
    'hardware wholesalers in Manchester' => 'hardware wholesalers',
    'importadores de autopeças em São Paulo' => 'importadores de autopeças',
    'auto parts distributors in Los Angeles' => 'auto parts distributors',
    'consumer electronics wholesalers in Singapore' => 'consumer electronics distributors',
    'LED lighting importers in Los Angeles' => 'LED lighting distributors',
    'building materials suppliers in Dubai' => 'building materials suppliers',
    'furniture importers in New York' => 'furniture wholesalers',
    "importateurs d'emballage à Paris" => "importateurs d'emballage",
    'chemical distributors in Amsterdam' => 'chemical distributors',
    'Kunststoff Großhändler in Berlin' => 'Kunststoff Großhändler',
    'importatori di tessuti a Roma' => 'importatori di tessuti',
    'grossistes de vêtements à Paris' => 'grossistes de vêtements',
    '化粧品 卸売 東京' => '化粧品 卸売',
    'food importers in Toronto' => 'food distributors',
    'medical device distributors in Sydney' => 'medical device distributors',
    'distribuidores de paneles solares en Madrid' => 'distribuidores de paneles solares',
    'pet product wholesalers in Melbourne' => 'pet product wholesalers',
    'wholesale distributors in Singapore' => 'B2B wholesale distributors',
    'import export companies in London' => 'import export trading companies',
    'freight forwarders in Amsterdam' => 'freight forwarders',
    'ecommerce fulfillment companies in Miami' => 'ecommerce fulfillment companies',
    'gift wholesalers in Rome' => 'gift wholesalers',
    '生活雑貨 卸売 大阪' => '生活雑貨 卸売',
    'industrial machinery importers' => 'industrial machinery importers',
    'construction equipment distributors' => 'construction equipment distributors',
    'hardware wholesalers' => 'hardware wholesalers',
    'importadores de autopeças' => 'importadores de autopeças',
    'auto parts distributors' => 'auto parts distributors',
    'consumer electronics wholesalers' => 'consumer electronics distributors',
    'LED lighting importers' => 'LED lighting distributors',
    'building materials suppliers' => 'building materials suppliers',
    'furniture importers' => 'furniture wholesalers',
    "importateurs d'emballage" => "importateurs d'emballage",
    'chemical distributors' => 'chemical distributors',
    'Kunststoff Großhändler' => 'Kunststoff Großhändler',
    'importatori di tessuti' => 'importatori di tessuti',
    'grossistes de vêtements' => 'grossistes de vêtements',
    '化粧品 卸売' => '化粧品 卸売',
    'food importers' => 'food distributors',
    'medical device distributors' => 'medical device distributors',
    'distribuidores de paneles solares' => 'distribuidores de paneles solares',
    'pet product wholesalers' => 'pet product wholesalers',
    'wholesale distributors' => 'B2B wholesale distributors',
    'import export companies' => 'import export trading companies',
    'freight forwarders' => 'freight forwarders',
    'ecommerce fulfillment companies' => 'ecommerce fulfillment companies',
    'gift wholesalers' => 'gift wholesalers',
    '生活雑貨 卸売' => '生活雑貨 卸売',
];

$select = $pdo->prepare('SELECT id, user_id, keyword, industry_id, country_id, language_code FROM keywords WHERE keyword = ?');
$duplicate = $pdo->prepare('SELECT COUNT(*) FROM keywords WHERE user_id = ? AND keyword = ? AND COALESCE(industry_id,0) = ? AND COALESCE(country_id,0) = ? AND language_code = ? AND id <> ?');
$update = $pdo->prepare('UPDATE keywords SET keyword = ? WHERE id = ?');
$updated = 0;
$skipped = 0;
$pdo->beginTransaction();
try {
    foreach ($map as $old => $new) {
        if ($old === $new) continue;
        $select->execute([$old]);
        foreach ($select->fetchAll() as $row) {
            $duplicate->execute([$row['user_id'], $new, (int) $row['industry_id'], (int) $row['country_id'], $row['language_code'], $row['id']]);
            if ((int) $duplicate->fetchColumn() > 0) {
                $skipped++;
                echo "Skip duplicate for user {$row['user_id']}: {$old}\n";
                continue;
            }
            $update->execute([$new, $row['id']]);
            $updated++;
        }
    }
    $pdo->commit();
    echo "Optimized {$updated} keywords; skipped {$skipped} duplicates.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
