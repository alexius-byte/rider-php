<?php

declare(strict_types=1);

namespace Rider\System\Router;

use ReflectionException;

abstract class AbstractRoute implements RouteInterface
{
    private Router $router;

    final public function boot(Router $router): void
    {
        $this->router = $router;
        $this->run();
    }

    protected function get(string $path, array|callable $handler): void
    {
        $this->router->get($path, $handler);
    }

    protected function post(string $path, array|callable $handler): void
    {
        $this->router->post($path, $handler);
    }

    protected function put(string $path, array|callable $handler): void
    {
        $this->router->put($path, $handler);
    }

    protected function delete(string $path, array|callable $handler): void
    {
        $this->router->delete($path, $handler);
    }

    /*** @throws ReflectionException */
    protected function tracker(HttpMethod $method, string $prefix, string $controller): void
    {
        $this->router->tracker($method, $prefix, $controller);
    }

    protected function group(string $prefix): void
    {
        $this->router->group($prefix);
    }

    protected function resetGroup(): void
    {
        $this->router->resetGroup();
    }

    protected function middleware(string ...$middlewares): void
    {
        $this->router->middleware(...$middlewares);
    }

    protected function resetMiddleware(): void
    {
        $this->router->resetMiddleware();
    }
}
