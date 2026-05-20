<?php

declare(strict_types=1);

namespace Rider\System\Router\Middleware;

abstract class AbstractMiddleware implements MiddlewareInterface
{
    protected function json(array $data, int $status = 200): bool
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return false;
    }

    protected function redirect(string $url, int $status = 302): bool
    {
        http_response_code($status);
        header("Location: $url");
        return false;
    }
}
