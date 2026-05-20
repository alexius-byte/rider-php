<?php

declare(strict_types=1);

namespace Rider\System\Http;

use Rider\System\Http\Exception\ClientException;

class Client
{
    private const BODYLESS = ['GET', 'HEAD', 'DELETE', 'OPTIONS'];

    private string $method = 'GET';
    private string $url = '';
    private array $headers = [];
    private array $data = [];
    private string|null $body = null;
    private string|null $proxy = null;
    private bool $verifySsl = true;
    private int $timeout = 30;

    public function get(string $url): static
    {
        $this->method = 'GET';
        $this->url = $url;
        return $this;
    }

    public function post(string $url): static
    {
        $this->method = 'POST';
        $this->url = $url;
        return $this;
    }

    public function put(string $url): static
    {
        $this->method = 'PUT';
        $this->url = $url;
        return $this;
    }

    public function patch(string $url): static
    {
        $this->method = 'PATCH';
        $this->url = $url;
        return $this;
    }

    public function delete(string $url): static
    {
        $this->method = 'DELETE';
        $this->url = $url;
        return $this;
    }

    public function json(bool $enabled = true): static
    {
        if ($enabled) {
            $this->headers['Accept'] = 'application/json';
            $this->headers['Content-Type'] = 'application/json';
        } else {
            unset($this->headers['Accept'], $this->headers['Content-Type']);
        }
        return $this;
    }

    public function headers(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function data(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function jsonBody(array $data): static
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new ClientException('Failed to encode JSON body');
        }

        $this->body = $encoded;
        return $this;
    }

    public function basicAuthorization(string $username, string $password): static
    {
        $this->headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");
        return $this;
    }

    public function bearerToken(string $token): static
    {
        $this->headers['Authorization'] = 'Bearer ' . $token;
        return $this;
    }

    public function proxy(string $proxy): static
    {
        $this->proxy = $proxy;
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function ignoreSsl(): static
    {
        $this->verifySsl = false;
        return $this;
    }

    public function execute(): ClientResponse
    {
        if ($this->url === '') {
            throw new ClientException('No URL defined');
        }

        $url = $this->url;
        $headers = $this->headers;
        $requestBody = $this->body;

        if ($this->data !== []) {
            if (in_array($this->method, self::BODYLESS, true)) {
                $separator = str_contains($url, '?') ? '&' : '?';
                $url .= $separator . http_build_query($this->data);
            } else {
                $requestBody = http_build_query($this->data);
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }

        $ch = curl_init($url);

        if ($ch === false) {
            throw new ClientException('Failed to initialize cURL');
        }

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $this->method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : false,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
        ];

        if ($this->proxy !== null) {
            $opts[CURLOPT_PROXY] = $this->proxy;
        }

        curl_setopt_array($ch, $opts);

        if ($requestBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($handle, string $header) use (&$responseHeaders): int {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($header);
        });

        try {
            $responseBody = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
        } finally {
            curl_close($ch);
        }

        if ($responseBody === false) {
            throw new ClientException("cURL error: {$error}");
        }

        $response = new ClientResponse($status, $responseBody, $responseHeaders);

        if (!$response->ok()) {
            throw new ClientException("HTTP error: {$status}", $response);
        }

        return $response;
    }
}
