<?php

declare(strict_types=1);

namespace Rider\System\Jwt;

use Exception;

class TokenGenerator
{
    public static function createSecret(int $length = 32): string
    {
        try {
            return bin2hex(random_bytes($length));
        } catch (Exception) {
            return self::generateGenericToken($length);
        }
    }

    private static function generateGenericToken(int $length): string
    {
        $token = '';
        for ($i = 0; $i < $length * 2; $i++) {
            $token .= dechex(mt_rand(0, 15));
        }
        return $token;
    }
}