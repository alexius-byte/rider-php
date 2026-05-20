<?php

declare(strict_types=1);

namespace Rider\System\Uuid;

class Uuid
{
    public static function v4(): string
    {
        $bytes = random_bytes(16);

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    public static function v7(): string
    {
        $ms = (int) floor(microtime(true) * 1000);
        $tsBytes = substr(pack('J', $ms), 2);
        $rand = random_bytes(10);

        $rand[0] = chr((ord($rand[0]) & 0x0f) | 0x70);
        $rand[1] = chr((ord($rand[1]) & 0x3f) | 0x80);

        $hex = bin2hex($tsBytes . $rand);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}