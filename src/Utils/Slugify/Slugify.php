<?php

declare(strict_types=1);

namespace Rider\System\Utils\Slugify;

class Slugify
{
    public static function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = strtolower((string) $text);
        $text = (string) preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = (string) preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }
}