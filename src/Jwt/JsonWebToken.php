<?php

declare(strict_types=1);

namespace Rider\System\Jwt;

use Rider\System\Jwt\Exception\JwtException;
use Rider\System\Jwt\Exception\JwtExpiredException;

class JsonWebToken
{
    private const ALGORITHMS = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    private const MIN_KEY_LENGTH = 32;

    private readonly string $alg;
    private readonly string $key;

    public function __construct(string $key, string $alg = 'HS256')
    {
        if (!isset(self::ALGORITHMS[$alg])) {
            throw new JwtException("Unsupported algorithm: {$alg}");
        }

        if (strlen($key) < self::MIN_KEY_LENGTH) {
            throw new JwtException('Key must be at least ' . self::MIN_KEY_LENGTH . ' bytes');
        }

        $this->key = $key;
        $this->alg = $alg;
    }

    public function hash(string $text): string
    {
        return hash_hmac("sha256", $text, $this->key);
    }

    public function checkHash(string $text, string $hash): bool
    {
        return hash_equals($hash, $this->hash($text));
    }

    public function encode(array $payload, string $expires): string
    {
        if (!isset($payload['iat'])) {
            $payload['iat'] = time();
        }

        $payload['exp'] = time() + $this->parseExpiry($expires);

        $headerJson = json_encode(['alg' => $this->alg, 'typ' => 'JWT'], JSON_UNESCAPED_UNICODE);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($headerJson === false || $payloadJson === false) {
            throw new JwtException('Failed to encode token');
        }

        $header = $this->base64url($headerJson);
        $body = $this->base64url($payloadJson);
        $signature = $this->sign("{$header}.{$body}");

        return "{$header}.{$body}.{$signature}";
    }

    public function decode(string $token): object
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new JwtException('Invalid token structure');
        }

        [$header, $body, $signature] = $parts;

        $headerData = json_decode($this->base64UrlDecode($header), true);

        if (!is_array($headerData) || ($headerData['alg'] ?? null) !== $this->alg) {
            throw new JwtException('Invalid token algorithm');
        }

        if (!hash_equals($this->sign("{$header}.{$body}"), $signature)) {
            throw new JwtException('Invalid token signature');
        }

        $payload = json_decode($this->base64UrlDecode($body));

        if (!is_object($payload)) {
            throw new JwtException('Invalid token payload');
        }

        if (isset($payload->exp)) {
            if (!is_int($payload->exp)) {
                throw new JwtException('Invalid token payload');
            }

            if ($payload->exp <= time()) {
                throw new JwtExpiredException($payload);
            }
        }

        return $payload;
    }

    private function sign(string $data): string
    {
        return $this->base64url(
            hash_hmac(self::ALGORITHMS[$this->alg], $data, $this->key, true)
        );
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function parseExpiry(string $expires): int
    {
        if (!preg_match('/^(\d+)([smhd])$/', $expires, $matches)) {
            throw new JwtException("Invalid expiry format: {$expires}");
        }

        return match ($matches[2]) {
            's' => (int)$matches[1],
            'm' => (int)$matches[1] * 60,
            'h' => (int)$matches[1] * 3600,
            'd' => (int)$matches[1] * 86400,
        };
    }
}
