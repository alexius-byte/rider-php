<?php

declare(strict_types=1);

namespace Rider\System\Http;

class HttpResponse
{
    private int $status = 200;
    private array $customHeaders = [];

    private const SECURITY_HEADERS = [
        'X-Content-Type-Options'  => 'nosniff',
        'X-Frame-Options'         => 'DENY',
        'X-XSS-Protection'        => '0',
        'Referrer-Policy'         => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'",
    ];

    public function status(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->customHeaders[$name] = $value;
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->customHeaders[$name] = $value;
        }
        return $this;
    }

    public function json(array $data, int $status = 200): never
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->status = $status;
        $this->emit('application/json');
        echo $encoded !== false ? $encoded : '{}';
        exit;
    }

    public function html(string $content, int $status = 200): never
    {
        $this->status = $status;
        $this->emit('text/html; charset=UTF-8');
        echo $content;
        exit;
    }

    public function text(string $content, int $status = 200): never
    {
        $this->status = $status;
        $this->emit('text/plain; charset=UTF-8');
        echo $content;
        exit;
    }

    public function redirect(string $url, int $status = 302): never
    {
        $this->status = $status;
        $this->emit(null);
        header('Location: ' . $url);
        exit;
    }

    public function noContent(): never
    {
        $this->status = 204;
        $this->emit(null);
        exit;
    }

    public function download(string $filePath, string $filename): never
    {
        $realPath = realpath($filePath);

        if ($realPath === false || !is_file($realPath)) {
            $this->status = 404;
            $this->emit('application/json');
            echo '{"error":"file not found"}';
            exit;
        }

        $safeFilename = str_replace(['"', '\\'], ['\"', '\\\\'], $filename);

        $this->emit(null);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($realPath));
        readfile($realPath);
        exit;
    }

    private function emit(?string $contentType): void
    {
        http_response_code($this->status);

        foreach (self::SECURITY_HEADERS as $name => $value) {
            header($name . ': ' . $value);
        }

        foreach ($this->customHeaders as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($contentType !== null) {
            header('Content-Type: ' . $contentType);
        }
    }
}
