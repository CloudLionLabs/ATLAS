<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

final class User
{
    public function all(): array
    {
        return Database::connection()->query('SELECT id, username, email, display_name, role, is_active, last_login_at, created_at, updated_at FROM users ORDER BY id')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row ?: null;
    }

    public function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function create(string $username, string $displayName, string $password, string $role = 'member', ?string $email = null): int
    {
        $username = trim($username);
        $displayName = trim($displayName) ?: $username;
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $username)) {
            throw new \InvalidArgumentException('用户名需为 3–40 位字母、数字、下划线、点或短横线。');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('密码至少需要 8 位。');
        }
        if (!in_array($role, ['admin', 'member'], true)) {
            $role = 'member';
        }
        try {
            $stmt = Database::connection()->prepare('INSERT INTO users (username, email, display_name, password_hash, role, is_active, created_at, updated_at) VALUES (?,?,?,?,?,1,?,?)');
            $stmt->execute([$username, $email ?: null, $displayName, password_hash($password, PASSWORD_DEFAULT), $role, now_utc(), now_utc()]);
            return (int) Database::connection()->lastInsertId();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                throw new \InvalidArgumentException('用户名或邮箱已存在。');
            }
            throw $e;
        }
    }

    public function update(int $id, string $displayName, string $role, bool $active, ?string $email = null): void
    {
        if (!in_array($role, ['admin', 'member'], true)) {
            $role = 'member';
        }
        $stmt = Database::connection()->prepare('UPDATE users SET display_name = ?, email = ?, role = ?, is_active = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([trim($displayName), $email ?: null, $role, $active ? 1 : 0, now_utc(), $id]);
    }

    public function setPassword(int $id, string $password): void
    {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('密码至少需要 8 位。');
        }
        Database::connection()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), now_utc(), $id]);
    }

    public function verifyPassword(int $id, string $password): bool
    {
        $stmt = Database::connection()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        return is_string($hash) && $hash !== '' && password_verify($password, $hash);
    }
}
