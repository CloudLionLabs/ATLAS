<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class KeywordSeeder
{
    /**
     * Copy the B2B starter taxonomy into one tenant's own keyword library.
     * Each inserted row carries that tenant's user_id; no rows are shared.
     */
    public function seedForUser(int $userId): int
    {
        if ($userId <= 0) return 0;
        $taxonomy = require base_path('database/b2b_taxonomy.php');
        $pdo = Database::connection();

        $industries = [];
        foreach ($pdo->query('SELECT id, code FROM industries') as $row) {
            $industries[(string) $row['code']] = (int) $row['id'];
        }
        $countries = [];
        foreach ($pdo->query('SELECT id, iso2 FROM countries') as $row) {
            $countries[strtoupper((string) $row['iso2'])] = (int) $row['id'];
        }

        $exists = $pdo->prepare('SELECT COUNT(*) FROM keywords WHERE user_id = ? AND lower(keyword) = lower(?) AND COALESCE(industry_id, 0) = ? AND COALESCE(country_id, 0) = ? AND language_code = ?');
        $insert = $pdo->prepare('INSERT INTO keywords (user_id, industry_id, country_id, language_code, keyword, is_active, notes, created_at) VALUES (?,?,?,?,?,1,?,?)');
        $added = 0;
        foreach ($taxonomy['keywords'] as $row) {
            [$industryCode, $iso2, $lang, $keyword, $notes] = $row;
            $industryId = $industries[$industryCode] ?? 0;
            $countryId = $countries[strtoupper((string) $iso2)] ?? 0;
            if (!$industryId || !$countryId) continue;
            $exists->execute([$userId, $keyword, $industryId, $countryId, $lang]);
            if ((int) $exists->fetchColumn() > 0) continue;
            $insert->execute([$userId, $industryId, $countryId, $lang, $keyword, $notes, now_utc()]);
            $added++;
        }
        return $added;
    }
}
