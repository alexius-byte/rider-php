<?php

declare(strict_types=1);

namespace Rider\System\Env;

class DotenvCreate
{
    private array $lines = [];

    public function __construct(private readonly string $path) {}

    public function comment(string $text): void
    {
        $this->lines[] = "# $text";
    }

    public function add(string $key, string $value): void
    {
        if (preg_match('/[\s#]/', $value)) {
            $value = '"' . $value . '"';
        }

        $this->lines[] = "$key=$value";
    }

    public function lineBreak(): void
    {
        $this->lines[] = '';
    }

    public function create(): bool
    {
        $content = implode(PHP_EOL, $this->lines) . PHP_EOL;
        return file_put_contents($this->path, $content) !== false;
    }
}
