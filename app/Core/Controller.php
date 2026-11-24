<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected bool $requiresAuth = true;

    public function __construct()
    {
        if ($this->requiresAuth && !Auth::check()) {
            $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $wantsJson = str_starts_with($uri, '/api/')
                || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
            if ($wantsJson) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'data' => null, 'error' => ['code' => 'UNAUTHENTICATED', 'message' => '请先登录']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            flash('warning', '请先登录后继续。');
            $this->redirect('/login');
        }
    }

    protected function userId(): int
    {
        return Auth::id();
    }

    protected function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/')) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'data' => null, 'error' => ['code' => 'FORBIDDEN', 'message' => '需要管理员权限']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $this->flashError('该操作需要管理员权限。');
            $this->redirect('/');
        }
    }

    protected function view(string $template, array $data = [], string $layout = 'Admin/Layouts/app'): string
    {
        $data['pageTitle'] = $data['pageTitle'] ?? config('name');
        return View::render($template, $data, $layout);
    }

    protected function json(array $data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function ok(mixed $data = null, array $meta = []): string
    {
        return $this->json(['success' => true, 'data' => $data, 'error' => null, 'meta' => $meta]);
    }

    protected function fail(string $code, string $message, int $status = 400): string
    {
        return $this->json([
            'success' => false,
            'data' => null,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }

    protected function redirect(string $path, int $status = 303): never
    {
        $path = '/' . ltrim($path, '/');
        header('Location: ' . $path, true, $status);
        exit;
    }

    protected function back(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '/';
        $path = parse_url($ref, PHP_URL_PATH) ?: '/';
        $this->redirect($path);
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    protected function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    protected function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    protected function boolean(string $key): bool
    {
        return in_array((string) $this->input($key, '0'), ['1', 'on', 'true', 'yes'], true);
    }

    protected function rememberOld(array $values): void
    {
        $_SESSION['_old'] = $values;
    }

    protected function clearOld(): void
    {
        unset($_SESSION['_old']);
    }

    protected function flashSuccess(string $message): void
    {
        flash('success', $message);
    }

    protected function flashWarning(string $message): void
    {
        flash('warning', $message);
    }

    protected function flashError(string $message): void
    {
        flash('error', $message);
    }
}
