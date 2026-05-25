<?php

declare(strict_types=1);

namespace Rider\System\Utils\Cors;

class Cors
{
    public static function allow(
        string|array $origins = '*',
        array $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization', 'X-Requested-With'],
        bool $credentials = false,
        int $maxAge = 86400
    ): void {
        $allowedOrigin = self::resolveOrigin($origins);

        if ($allowedOrigin === null) {
            return;
        }

        header("Access-Control-Allow-Origin: $allowedOrigin");

        if ($allowedOrigin !== '*') {
            header('Vary: Origin');
        }

        if ($credentials && $allowedOrigin !== '*') {
            header('Access-Control-Allow-Credentials: true');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
            header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
            header("Access-Control-Max-Age: $maxAge");
            http_response_code(204);
            exit;
        }
    }

    private static function resolveOrigin(string|array $origins): string|null
    {
        if ($origins === '*') {
            return '*';
        }

        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;

        if ($requestOrigin === null) {
            return null;
        }

        if (in_array($requestOrigin, (array) $origins, true)) {
            return $requestOrigin;
        }

        return null;
    }
}
