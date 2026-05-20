<?php

declare(strict_types=1);

namespace Rider\System\Router;

interface RouteInterface
{
    public function boot(Router $router): void;
    public function run(): void;
}
