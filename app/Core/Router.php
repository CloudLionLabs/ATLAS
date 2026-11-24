<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, array $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'regex' => $this->compile($path),
            'handler' => $handler,
        ];
    }

    private function compile(string $path): string
    {
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m): string {
            return '(?P<' . $m[1] . '>[^/]+)';
        }, rtrim($path, '/') ?: '/');
        return '#^' . $regex . '$#';
    }

    public function dispatch(string $method, string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        foreach ($this->routes as $route) {
            $methodMatches = $route['method'] === $method || ($method === 'HEAD' && $route['method'] === 'GET');
            if (!$methodMatches || !preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($method === 'POST' && !$this->validCsrf()) {
                http_response_code(419);
                return View::render('Common/View/error', [
                    'title' => '会话已过期',
                    'code' => '419',
                    'message' => '安全令牌已失效，请刷新页面后重试。',
                ]);
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $route['handler'];
            $controller = new $class();
            $result = $controller->{$action}($params);
            return is_string($result) ? $result : '';
        }

        http_response_code(404);
        return View::render('Common/View/error', [
            'title' => '未找到页面',
            'code' => '404',
            'message' => '这条数据链路不存在，或页面已经迁移。',
        ]);
    }

    private function validCsrf(): bool
    {
        $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return is_string($token) && $token !== '' && hash_equals((string) ($_SESSION['_csrf'] ?? ''), $token);
    }
}
