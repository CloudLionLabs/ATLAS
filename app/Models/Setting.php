<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Setting
{
    public function forUser(int $userId): array
    {
        if ($userId <= 0) return [];
        $stmt = Database::connection()->prepare('SELECT `key`, value FROM user_settings WHERE user_id = ?');
        $stmt->execute([$userId]);
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }

    public function get(int $userId, string $key, mixed $default = null): mixed
    {
        if ($userId <= 0) return $default;
        $stmt = Database::connection()->prepare('SELECT value FROM user_settings WHERE user_id = ? AND `key` = ?');
        $stmt->execute([$userId, $key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    }

    public function put(int $userId, string $key, string $value): void
    {
        Database::connection()->prepare('INSERT INTO user_settings (user_id, `key`, value, updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)')
            ->execute([$userId, $key, $value, now_utc()]);
    }
}
