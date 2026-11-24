<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = (int) ($_SESSION['auth_user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        try {
            $stmt = Database::connection()->prepare('SELECT id, username, email, display_name, role, is_active, last_login_at, created_at FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if ($row) {
                self::$user = $row;
                return self::$user;
            }
        } catch (\Throwable $e) {
            error_log('Atlas auth lookup failed: ' . $e->getMessage());
        }
        unset($_SESSION['auth_user_id']);
        return null;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function isMember(): bool
    {
        return self::check() && !self::isAdmin();
    }

    public static function attempt(string $login, string $password): bool
    {
        $login = trim($login);
        if ($login === '' || $password === '') {
            return false;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE (lower(username) = lower(?) OR lower(email) = lower(?)) AND is_active = 1 LIMIT 1');
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            try {
                Database::connection()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), now_utc(), $user['id']]);
            } catch (\Throwable) {
                // A successful login must not fail just because the hash upgrade did.
            }
        }
        self::loginById((int) $user['id']);
        return true;
    }

    public static function loginById(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([now_utc(), now_utc(), $id]);
        session_regenerate_id(true);
        $_SESSION['auth_user_id'] = $id;
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
        self::$user = null;
        self::$loaded = false;
        self::user();
    }

    public static function logout(): void
    {
        unset($_SESSION['auth_user_id'], $_SESSION['active_user_id']);
        self::$user = null;
        self::$loaded = false;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }

    /** Force a user for CLI tests without going through the login form. */
    public static function forceUser(?int $id): void
    {
        if ($id === null || $id <= 0) {
            unset($_SESSION['auth_user_id']);
        } else {
            $_SESSION['auth_user_id'] = $id;
        }
        self::$user = null;
        self::$loaded = false;
    }
}
