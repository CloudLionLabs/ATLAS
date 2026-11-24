<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function boot(): void
    {
        self::connection();
    }

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = (array) config('database');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? 'atlas_global');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $collation = (string) ($config['collation'] ?? 'utf8mb4_unicode_ci');

        if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new RuntimeException('数据库名称不合法。');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        try {
            try {
                $pdo = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // First boot: create the database if the configured MySQL user
                // has permission to do so.
                if ((int) $e->getCode() !== 1049) throw $e;
                $server = new PDO("mysql:host={$host};port={$port};charset={$charset}", $username, $password, $options);
                $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$collation}");
                $pdo = new PDO($dsn, $username, $password, $options);
            }

            $pdo->exec("SET NAMES {$charset} COLLATE {$collation}");
            $pdo->exec("SET time_zone = '+00:00'");
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");

            self::$pdo = $pdo;
            self::ensureSchema($pdo);
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('MySQL 连接失败：' . $e->getMessage() . '。请检查 .env 中的 DB_HOST、DB_DATABASE、DB_USERNAME、DB_PASSWORD。', 0, $e);
        }
    }

    private static function ensureSchema(PDO $pdo): void
    {
        $hasUsers = false;
        $hasCountries = false;
        try {
            $hasUsers = (bool) $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users' LIMIT 1")->fetchColumn();
            $hasCountries = (bool) $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'countries' LIMIT 1")->fetchColumn();
        } catch (PDOException $e) {
            error_log('Atlas MySQL schema probe failed: ' . $e->getMessage());
        }

        if (!$hasUsers || !$hasCountries) {
            $schemaFile = base_path('database/schema_mysql.sql');
            if (!is_file($schemaFile)) {
                throw new RuntimeException('缺少 MySQL 表结构文件 database/schema_mysql.sql');
            }
            $sql = (string) file_get_contents($schemaFile);
            foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
                $statement = trim($statement);
                if ($statement === '') continue;
                $pdo->exec($statement);
            }
        }

        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM countries')->fetchColumn();
            if ($count === 0) {
                $seeder = static function (PDO $pdo): void {
                    require base_path('database/seed.php');
                };
                $seeder($pdo);
            }
        } catch (PDOException $e) {
            error_log('Atlas MySQL seed check skipped: ' . $e->getMessage());
        }
    }

    public static function isFresh(): bool
    {
        try {
            $pdo = self::connection();
            return !(bool) $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users' LIMIT 1")->fetchColumn();
        } catch (\Throwable) {
            return true;
        }
    }
}
