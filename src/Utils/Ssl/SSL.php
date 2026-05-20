<?php

declare(strict_types=1);

namespace Rider\System\Utils\Ssl;

class SSL
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    public static function encode(string $data, string $key): string
    {
        $iv = random_bytes(self::IV_LEN);
        $tag = '';

        $ciphertext = openssl_encrypt($data, self::CIPHER, self::deriveKey($key), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);

        if ($ciphertext === false) {
            throw new SslException('Encryption failed');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decode(string $data, string $key): string
    {
        $raw = base64_decode($data, true);

        if ($raw === false || strlen($raw) < self::IV_LEN + self::TAG_LEN) {
            throw new SslException('Invalid encrypted data');
        }

        $iv = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::deriveKey($key), OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            throw new SslException('Decryption failed');
        }

        return $plaintext;
    }

    public static function generateKeyPair(string $directory, string $publicName, string $secretName, int $bits = 2048): void
    {
        if (!is_dir($directory)) {
            throw new SslException("Directory does not exist: {$directory}");
        }

        $dir       = rtrim($directory, '/\\');
        $publicPath = "{$dir}/{$publicName}.pem";
        $secretPath = "{$dir}/{$secretName}.pem";

        $resource = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new SslException('Failed to generate key pair');
        }

        if (!openssl_pkey_export($resource, $privateKey)) {
            throw new SslException('Failed to export private key');
        }

        $details = openssl_pkey_get_details($resource);

        if ($details === false) {
            throw new SslException('Failed to extract public key');
        }

        if (file_put_contents($secretPath, $privateKey) === false) {
            throw new SslException("Failed to save private key: {$secretPath}");
        }

        chmod($secretPath, 0600);

        if (file_put_contents($publicPath, $details['key']) === false) {
            throw new SslException("Failed to save public key: {$publicPath}");
        }
    }

    private static function deriveKey(string $key): string
    {
        return hash('sha256', $key, true);
    }
}
