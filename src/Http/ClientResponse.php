<?php

declare(strict_types=1);

namespace Rider\System\Http;

class ClientResponse
{
    public function __construct(
        private readonly int $status,
        private readonly string $body,
        private readonly array $headers,
    ) {}

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(): array|object|null
    {
        return json_decode($this->body);
    }

    public function header(string $name): string|null
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
