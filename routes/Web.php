<?php
declare(strict_types=1);

use App\Core\Router;

$router = new Router();
require __DIR__ . '/Auth.php';
require __DIR__ . '/Admin.php';
return $router;
