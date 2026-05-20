<?php

declare(strict_types=1);

namespace Rider\System\Env;

class Dotenv
{
    private function __construct(private readonly string $path)
    {
    }

    public static function load(string $path): void
    {
        (new self($path))->parse();
    }

    private function parse(): void
    {
        $resolved = realpath($this->path);

        if ($resolved === false || !is_readable($resolved)) {
            return;
        }

        $lines = file($resolved, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $raw] = explode('=', $line, 2);

            $key = trim($key);

            if (str_starts_with($key, 'export ')) {
                $key = trim(substr($key, 7));
            }

            if (!$this->isValidKey($key) || array_key_exists($key, $_ENV)) {
                continue;
            }

            $value = $this->cast(trim($raw));

            $_ENV[$key] = $value;
            putenv($key . '=' . $this->stringify($value));
        }
    }

    private function isValidKey(string $key): bool
    {
        return $key !== '' && preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key) === 1;
    }

    private function cast(string $raw): string|int|float|bool|null
    {
        if (strlen($raw) >= 2) {
            $first = $raw[0];
            $last = $raw[-1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($raw, 1, -1);
            }
        }

        if (str_contains($raw, ' #')) {
            $raw = trim(explode(' #', $raw, 2)[0]);
        }

        return match (strtolower($raw)) {
            'true' => true,
            'false' => false,
            'null' => null,
            '' => '',
            default => $this->toNumber($raw),
        };
    }

    private function toNumber(string $raw): string|int|float
    {
        if (!is_numeric($raw)) {
            return $raw;
        }

        return str_contains($raw, '.') ? (float)$raw : (int)$raw;
    }

    private function stringify(string|int|float|bool|null $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => '',
            default => (string)$value,
        };
    }
}
