<?php

namespace App\Core;

use App\Core\Auth\AuthMiddleware;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable, auth: bool}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, bool $auth = false): void
    {
        $this->add('GET', $pattern, $handler, $auth);
    }

    public function post(string $pattern, callable $handler, bool $auth = false): void
    {
        $this->add('POST', $pattern, $handler, $auth);
    }

    private function add(string $method, string $pattern, callable $handler, bool $auth): void
    {
        $this->routes[] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler, 'auth' => $auth];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = $this->match($route['pattern'], $request->path);
            if ($params === null) {
                continue;
            }

            if ($route['auth'] && !AuthMiddleware::check($request)) {
                Response::error('Unauthorized', 401);
                return;
            }

            ($route['handler'])($request, $params);
            return;
        }

        Response::error('Not found', 404);
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $regex = \preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);

        if (\preg_match('#^' . $regex . '$#', $path, $m)) {
            return \array_filter($m, static fn($key) => !\is_int($key), \ARRAY_FILTER_USE_KEY);
        }

        return null;
    }
}
