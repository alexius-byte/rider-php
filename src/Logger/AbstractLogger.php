<?php

declare(strict_types=1);

namespace Rider\System\Logger;

abstract class AbstractLogger
{
    protected string $name;
    protected string $filePath;

    public function __construct($filePath, $name)
    {
        $this->filePath = $filePath;
        $this->name = $name;
    }

    private function path(): string
    {
        return rtrim($this->filePath, '/\\') . DIRECTORY_SEPARATOR . $this->name . '.log';
    }

    public function log(string $key, mixed $value): void
    {
        $entry = json_encode(
            ['at' => date('Y-m-d H:i:s'), 'key' => $key, 'value' => $value],
            JSON_UNESCAPED_UNICODE
        );

        file_put_contents($this->path(), $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function error($value): void
    {
        $this->log("ERROR", $value);
    }

    public function info($value): void
    {
        $this->log("INFO", $value);
    }

    public function warning($value): void
    {
        $this->log("WARNING", $value);
    }

    public function all($reverse = true): array
    {
        $path = $this->path();

        if (!file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $data = array_map(fn(string $line) => json_decode($line, true), $lines ?: []);
        if ($reverse) {
            return array_reverse($data);
        }
        return $data;
    }
}