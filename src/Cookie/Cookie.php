<?php

declare(strict_types=1);

namespace Rider\System\Cookie;

class Cookie
{
    public static function set(string $key, string $value, string $expiration): void
    {
        setcookie($key, $value, time() + self::parseExpiry($expiration), '/', '', true, true);
    }

    public static function get(string $key): string|null
    {
        $value = filter_input(INPUT_COOKIE, $key);
        return $value === null || $value === false ? null : $value;
    }

    public static function delete(string $key): void
    {
        setcookie($key, '', time() - 3600, '/', '', true, true);
        unset($_COOKIE[$key]);
    }

    private static function parseExpiry(string $expires): int
    {
        if (!preg_match('/^(\d+)([smhd])$/', $expires, $matches)) {
            throw new CookieException("Invalid expiry format: {$expires}");
        }

        return match ($matches[2]) {
            's' => (int) $matches[1],
            'm' => (int) $matches[1] * 60,
            'h' => (int) $matches[1] * 3600,
            'd' => (int) $matches[1] * 86400,
        };
    }
}