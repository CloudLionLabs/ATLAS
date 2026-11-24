<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$taxonomy = require BASE_PATH . '/database/b2b_taxonomy.php';
$legacyCodes = [
    'home-services', 'plumbing', 'electrical', 'hvac', 'roofing', 'cleaning',
    'professional', 'accounting', 'legal', 'marketing', 'it-services',
    'food', 'restaurants', 'cafes', 'hotels', 'health', 'dentists', 'physio',
    'gyms', 'technology', 'software', 'web-design',
];

$pdo = db();
$adminId = (int) ($pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
if ($adminId <= 0) { fwrite(STDERR, "Run the MySQL schema/seed first\n"); exit(1); }
$pdo->beginTransaction();
try {
    $insert = $pdo->prepare('INSERT INTO industries (code, name, description, sort_order, is_active, created_at, updated_at) VALUES (?,?,?,?,1,?,?) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1, updated_at = VALUES(updated_at)');
    $translation = $pdo->prepare("INSERT INTO industry_translations (industry_id, language_code, name) VALUES (?, 'zh', ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
    $find = $pdo->prepare('SELECT id FROM industries WHERE code = ?');

    // Insert top-level nodes first, then their children.
    foreach ([true, false] as $topLevel) {
        foreach ($taxonomy['industries'] as $row) {
            [$code, $name, $nameZh, $description, $parentCode, $sortOrder] = $row;
            if (($parentCode === null) !== $topLevel) continue;
            $parentId = null;
            if ($parentCode !== null) {
                $find->execute([$parentCode]);
                $parentId = $find->fetchColumn();
                if (!$parentId) continue;
            }
            $insert->execute([$code, $name, $description, $sortOrder, now_utc(), now_utc()]);
            $find->execute([$code]);
            $industryId = (int) $find->fetchColumn();
            if ($industryId) {
                $pdo->prepare('UPDATE industries SET parent_id = ? WHERE id = ?')->execute([$parentId ?: null, $industryId]);
                $translation->execute([$industryId, $nameZh]);
            }
        }
    }

    // Deactivate the original local-services taxonomy. Records and history remain.
    $placeholders = implode(',', array_fill(0, count($legacyCodes), '?'));
    $pdo->prepare("UPDATE industries SET is_active = 0, updated_at = ? WHERE code IN ({$placeholders})")->execute([now_utc(), ...$legacyCodes]);
    $pdo->prepare("UPDATE keywords SET is_active = 0 WHERE industry_id IN (SELECT id FROM industries WHERE code IN ({$placeholders}))")->execute($legacyCodes);

    $industryMap = [];
    foreach ($pdo->query('SELECT id, code FROM industries') as $row) {
        $industryMap[$row['code']] = (int) $row['id'];
    }
    $countryMap = [];
    foreach ($pdo->query('SELECT id, iso2 FROM countries') as $row) {
        $countryMap[$row['iso2']] = (int) $row['id'];
    }

    $keywordCount = 0;
    $exists = $pdo->prepare('SELECT COUNT(*) FROM keywords WHERE user_id = ? AND lower(keyword) = lower(?) AND COALESCE(industry_id,0) = ? AND COALESCE(country_id,0) = ? AND language_code = ?');
    $keywordInsert = $pdo->prepare('INSERT INTO keywords (user_id, industry_id, country_id, language_code, keyword, notes, is_active, created_at) VALUES (?,?,?,?,?,?,1,?)');
    foreach ($taxonomy['keywords'] as $row) {
        [$industryCode, $iso2, $lang, $keyword, $notes] = $row;
        if (!isset($industryMap[$industryCode], $countryMap[$iso2])) continue;
        $exists->execute([$adminId, $keyword, $industryMap[$industryCode], $countryMap[$iso2], $lang]);
        if ((int) $exists->fetchColumn() > 0) continue;
        $keywordInsert->execute([$adminId, $industryMap[$industryCode], $countryMap[$iso2], $lang, $keyword, $notes, now_utc()]);
        $keywordCount++;
    }

    foreach ([
        ['email_finder_enabled', '1'],
        ['email_finder_max_pages', '3'],
        ['email_domain_blacklist', ''],
    ] as [$key, $value]) {
        $pdo->prepare('INSERT IGNORE INTO app_settings (`key`, value, updated_at) VALUES (?,?,?)')->execute([$key, $value, now_utc()]);
    }

    audit('taxonomy.b2b.apply', 'industry', null, ['industries' => count($taxonomy['industries']), 'keywords_added' => $keywordCount, 'legacy_deactivated' => count($legacyCodes)]);
    $pdo->commit();
    echo "B2B taxonomy applied: " . count($taxonomy['industries']) . " categories, {$keywordCount} new keywords.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Failed: " . $e->getMessage() . "\n");
    exit(1);
}
