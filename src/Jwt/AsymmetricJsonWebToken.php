<?php

declare(strict_types=1);

namespace Rider\System\Jwt;

use OpenSSLAsymmetricKey;
use Rider\System\Jwt\Exception\JwtException;
use Rider\System\Jwt\Exception\JwtExpiredException;

class AsymmetricJsonWebToken
{
    private const ALGORITHMS = [
        'RS256' => OPENSSL_ALGO_SHA256,
        'RS384' => OPENSSL_ALGO_SHA384,
        'RS512' => OPENSSL_ALGO_SHA512,
    ];

    private readonly ?OpenSSLAsymmetricKey $privateKey;
    private readonly ?OpenSSLAsymmetricKey $publicKey;
    private readonly string $alg;

    public function __construct(?string $privateKey = null, ?string $publicKey = null, string $alg = 'RS256')
    {
        if (!isset(self::ALGORITHMS[$alg])) {
            throw new JwtException("Unsupported algorithm: {$alg}");
        }

        if ($privateKey === null && $publicKey === null) {
            throw new JwtException('At least one key must be provided');
        }

        $this->alg = $alg;
        $this->privateKey = $privateKey !== null ? $this->loadPrivateKey($privateKey) : null;
        $this->publicKey = $publicKey !== null ? $this->loadPublicKey($publicKey) : null;
    }

    private function loadPrivateKey(string $key): OpenSSLAsymmetricKey
    {
        $parsed = openssl_pkey_get_private($key);

        if ($parsed === false) {
            throw new JwtException('Invalid private key');
        }

        return $parsed;
    }

    private function loadPublicKey(string $key): OpenSSLAsymmetricKey
    {
        $parsed = openssl_pkey_get_public($key);

        if ($parsed === false) {
            throw new JwtException('Invalid public key');
        }

        return $parsed;
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

        if (!$this->verify("{$header}.{$body}", $signature)) {
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
        if ($this->privateKey === null) {
            throw new JwtException('Private key is required to sign tokens');
        }

        $signature = '';

        if (!openssl_sign($data, $signature, $this->privateKey, self::ALGORITHMS[$this->alg])) {
            throw new JwtException('Failed to sign token');
        }

        return $this->base64url($signature);
    }

    private function verify(string $data, string $signature): bool
    {
        if ($this->publicKey === null) {
            throw new JwtException('Public key is required to verify tokens');
        }

        $raw = base64_decode(strtr($signature, '-_', '+/'));

        if ($raw === false) {
            return false;
        }

        $result = openssl_verify($data, $raw, $this->publicKey, self::ALGORITHMS[$this->alg]);

        if ($result === -1) {
            throw new JwtException('Signature verification error');
        }

        return $result === 1;
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
