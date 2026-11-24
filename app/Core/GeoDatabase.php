<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Dedicated read-only SQLite database for the global city dictionary.
 * Kept separate from the operational app database so it can be replaced or
 * rebuilt independently without touching business/job records.
 */
final class GeoDatabase
{
    private static ?PDO $pdo = null;
    private static bool $unavailable = false;

    public static function available(): bool
    {
        $path = (string) config('geo_database.path');
        return is_file($path) && is_readable($path);
    }

    public static function connection(): ?PDO
    {
        if (self::$unavailable) {
            return null;
        }
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $path = (string) config('geo_database.path');
        if (!is_file($path) || !is_readable($path)) {
            self::$unavailable = true;
            return null;
        }
        try {
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('PRAGMA query_only = ON');
            $pdo->exec('PRAGMA busy_timeout = 3000');
            self::$pdo = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            self::$unavailable = true;
            error_log('Geo database unavailable: ' . $e->getMessage());
            return null;
        }
    }

    public static function path(): string
    {
        return (string) config('geo_database.path');
    }
}
