<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Never let PHP notices/deprecations corrupt JSON API responses.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

use App\Core\Router;

try {
    require BASE_PATH . '/app/bootstrap.php';

    /** @var Router $router */
    $router = require BASE_PATH . '/routes/Web.php';
    echo $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    $isDebug = false;
    try {
        $isDebug = (bool) env('APP_DEBUG', false) || config('env') === 'development';
    } catch (Throwable) {
        $isDebug = false;
    }
    $detail = $isDebug
        ? $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()
        : '服务器内部错误，请检查本地日志。';
    $message = '数据库或服务暂时不可用。请确认 storage/ 可写，然后刷新页面。';
    try {
        echo App\Core\View::render('Common/View/error', [
            'title' => '系统异常',
            'code' => '500',
            'message' => $detail . "\n\n" . $message,
        ]);
    } catch (Throwable $renderError) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Atlas · 系统异常</title><body style="background:#04070d;color:#94a3b8;font-family:sans-serif;padding:48px"><h1 style="color:#fff">Atlas 系统异常</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><pre style="white-space:pre-wrap;color:#64748b">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</pre></body>';
    }
}
