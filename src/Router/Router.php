<?php

declare(strict_types=1);

namespace Rider\System\Router;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
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
    private static array $namedRoutes = [];

    public function get(string $path, array|callable $handler, string $name = ''): void
    {
        $this->addRoute(HttpMethod::GET, $path, $handler, $name);
    }

    public function post(string $path, array|callable $handler, string $name = ''): void
    {
        $this->addRoute(HttpMethod::POST, $path, $handler, $name);
    }

    public function put(string $path, array|callable $handler, string $name = ''): void
    {
        $this->addRoute(HttpMethod::PUT, $path, $handler, $name);
    }

    public function delete(string $path, array|callable $handler, string $name = ''): void
    {
        $this->addRoute(HttpMethod::DELETE, $path, $handler, $name);
    }

    public static function route(string $name): string
    {
        return self::$namedRoutes[$name] ?? '';
    }

    public function group(string $prefix): void
    {
        $this->currentGroup = rtrim('/' . ltrim($prefix, '/'), '/');
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

    private function addRoute(HttpMethod $method, string $path, array|callable $handler, string $name = ''): void
    {
        $path = $this->resolvePath($path);

        if ($name !== '') {
            self::$namedRoutes[$name] = $path;
        }

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

        foreach ($this->staticRoutes as $registeredMethod => $routes) {
            if ($registeredMethod !== $method && isset($routes[$path])) {
                $this->errorCode = 405;
                return;
            }
        }

        foreach ($this->dynamicRoutes as $registeredMethod => $routes) {
            if ($registeredMethod === $method) {
                continue;
            }
            foreach ($routes as [$pattern]) {
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

    private static array $dependencyCache = [];

    private function resolveConstructorDependencies(string $class): array
    {
        if (array_key_exists($class, self::$dependencyCache)) {
            return array_map(fn(string $type) => new $type(), self::$dependencyCache[$class]);
        }

        $constructor = (new ReflectionClass($class))->getConstructor();

        if ($constructor === null) {
            self::$dependencyCache[$class] = [];
            return [];
        }

        $types = [];
        foreach ($constructor->getParameters() as $param) {
            if ($param->isDefaultValueAvailable()) {
                continue;
            }
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $types[] = $type->getName();
            }
        }

        self::$dependencyCache[$class] = $types;
        return array_map(fn(string $type) => new $type(), $types);
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

            $deps = $this->resolveConstructorDependencies($class);
            (new $class(...$deps))->$method($request);
        } catch (SchemaException $e) {
            (new HttpResponse())->json(['error' => true, 'message' => array_values($e->errors())[0]], 422);
        } catch (RequestException $e) {
            (new HttpResponse())->json(['error' => true, 'message' => $e->getMessage()], 400);
        }
    }
}
