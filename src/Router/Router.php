<?php

declare(strict_types=1);

namespace Rider\System\Router;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Rider\System\Http\HttpResponse;
use Rider\System\Http\Request;
use Rider\System\Http\Exception\RequestException;
use Rider\System\Validation\Exception\SchemaException;

class Router
{
    private array $staticRoutes = [];
    private array $dynamicRoutes = [];
    private string $currentGroup = '';
    private array $currentMiddleware = [];
    private int|null $errorCode = null;

    public function get(string $path, array|callable $handler): void
    {
        $this->addRoute(HttpMethod::GET, $path, $handler);
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->addRoute(HttpMethod::POST, $path, $handler);
    }

    public function put(string $path, array|callable $handler): void
    {
        $this->addRoute(HttpMethod::PUT, $path, $handler);
    }

    public function delete(string $path, array|callable $handler): void
    {
        $this->addRoute(HttpMethod::DELETE, $path, $handler);
    }

    public function group(string $prefix): void
    {
        $this->currentGroup = rtrim($prefix, '/');
    }

    public function resetGroup(): void
    {
        $this->currentGroup = '';
    }

    public function middleware(string ...$middlewares): void
    {
        $this->currentMiddleware = $middlewares;
    }

    public function resetMiddleware(): void
    {
        $this->currentMiddleware = [];
    }

    private function resolvePath(string $path): string
    {
        $full = $this->currentGroup . '/' . ltrim($path, '/');
        return $full === '/' ? '/' : rtrim($full, '/');
    }

    private function addRoute(HttpMethod $method, string $path, array|callable $handler): void
    {
        $path = $this->resolvePath($path);

        if (!str_contains($path, '{')) {
            $this->staticRoutes[$method->value][$path] = [$handler, $this->currentMiddleware];
            return;
        }

        $pattern = preg_replace('/\{(\w+)}/', '(?P<$1>[^/]+)', $path);
        $this->dynamicRoutes[$method->value][] = ['#^' . $pattern . '$#', $handler, $this->currentMiddleware];
    }

    /** @throws ReflectionException */
    public function tracker(HttpMethod $method, string $prefix, string $controller): void
    {
        $reflection = new ReflectionClass($controller);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $refMethod) {
            if ($refMethod->getDeclaringClass()->getName() !== $controller) {
                continue;
            }
            if (str_starts_with($refMethod->getName(), '__')) {
                continue;
            }

            $this->addRoute($method, $prefix . '/' . $refMethod->getName(), [$controller, $refMethod->getName()]);
        }
    }

    public function addRouters(RouteInterface $group): void
    {
        $group->boot($this);
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        $parsed = parse_url($uri, PHP_URL_PATH);
        $path = rawurldecode($parsed ?? '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        if (isset($this->staticRoutes[$method][$path])) {
            [$handler, $middlewares] = $this->staticRoutes[$method][$path];
            if ($this->runMiddlewares($middlewares)) {
                $this->call($handler, []);
            }
            return;
        }

        foreach ($this->dynamicRoutes[$method] ?? [] as [$pattern, $handler, $middlewares]) {
            if (preg_match($pattern, $path, $matches)) {
                if ($this->runMiddlewares($middlewares)) {
                    $this->call($handler, array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
                }
                return;
            }
        }

        foreach (array_keys($this->staticRoutes) as $registeredMethod) {
            if ($registeredMethod !== $method && isset($this->staticRoutes[$registeredMethod][$path])) {
                $this->errorCode = 405;
                return;
            }
        }

        foreach (array_keys($this->dynamicRoutes) as $registeredMethod) {
            if ($registeredMethod === $method) {
                continue;
            }
            foreach ($this->dynamicRoutes[$registeredMethod] as [$pattern]) {
                if (preg_match($pattern, $path)) {
                    $this->errorCode = 405;
                    return;
                }
            }
        }

        $this->errorCode = 404;
    }

    public function hasError(): int|null
    {
        return $this->errorCode;
    }

    private function runMiddlewares(array $middlewares): bool
    {
        foreach ($middlewares as $middlewareClass) {
            if (!(new $middlewareClass())->run()) {
                return false;
            }
        }
        return true;
    }

    private function call(array|callable $handler, array $params): void
    {
        $request = new Request($params);

        try {
            if ($handler instanceof Closure) {
                $handler($request);
                return;
            }

            [$class, $method] = $handler;

            if (!method_exists($class, $method)) {
                $this->errorCode = 404;
                return;
            }

            (new $class())->$method($request);
        } catch (SchemaException $e) {
            (new HttpResponse())->json(['error' => true, 'message' => array_values($e->errors())[0]], 422);
        } catch (RequestException $e) {
            (new HttpResponse())->json(['error' => true, 'message' => $e->getMessage()], 400);
        }
    }
}
