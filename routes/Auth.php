<?php
declare(strict_types=1);

use App\Modules\Auth\Login\Controller\LoginController;

/** @var App\Core\Router $router */
$router->get('/login', [LoginController::class, 'show']);
$router->post('/login', [LoginController::class, 'login']);
$router->post('/logout', [LoginController::class, 'logout']);
