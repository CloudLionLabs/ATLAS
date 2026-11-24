<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require BASE_PATH . '/app/Support/helpers.php';
require BASE_PATH . '/app/Support/icons.php';
require BASE_PATH . '/app/Core/Autoloader.php';

App\Core\Config::load(BASE_PATH . '/config/app.php');

date_default_timezone_set((string) config('timezone', 'UTC'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) config('session_name', 'atlas_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

App\Core\Database::boot();
