<?php

declare(strict_types=1);

namespace Rider\System\Utils\Csrf;

use Rider\System\Jwt\TokenGenerator;

class Csrf
{
    public static function createToken(): void
    {
        if (empty($_SESSION["csrf_token"])) {
            $_SESSION["csrf_token"] = TokenGenerator::createSecret();
        }
    }

    public static function getToken(): string
    {
        return $_SESSION["csrf_token"] ?? "";
    }

    public static function checkToken(string $token): bool
    {
        $session_token = $_SESSION["csrf_token"] ?? null;
        if (empty($session_token)) {
            throw new CsrfException("The security token does not exist");
        }

        if (empty($token)) {
            throw new CsrfException("The security token was not sent");
        }

        if (!hash_equals($session_token, $token)) {
            throw new CsrfException("The security token does not match");
        }

        return true;
    }
}