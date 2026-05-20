<?php

declare(strict_types=1);

namespace Rider\System\Router\Middleware;

interface MiddlewareInterface
{
    public function run(): bool;
}
