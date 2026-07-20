<?php

declare(strict_types=1);

namespace Rider\System\Cache;

use DateInterval;
use DateTime;
use Rider\System\Cache\Exception\CacheException;

class FileCache implements CacheInterface
{
    private readonly string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $real = realpath($cacheDir);

        if ($real === false || !is_dir($real)) {
            throw new CacheException("Invalid cache directory");
        }

        if (!is_writable($real)) {
            throw new CacheException("Cache directory is not writable");
        }

        $this->cacheDir = $real;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->read($key);

        return $entry !== null ? $entry['value'] : $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $payload = json_encode(['value' => $value, 'expiry' => $this->resolveExpiry($ttl)]);
        $target = $this->path($key);
        $tmp = $target . '.' . getmypid() . '.tmp';

        if (file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return false;
        }

        return rename($tmp, $target);
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);

        if (file_exists($path)) {
            unlink($path);
        }

        return true;
    }

    public function clear(): bool
    {
        foreach (glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $file) {
            unlink($file);
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->read($key) !== null;
    }

    public function remember(string $key, null|int|DateInterval $ttl, callable $callback): mixed
    {
        $entry = $this->read($key);

        if ($entry !== null) {
            return $entry['value'];
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    private function read(string $key): ?array
    {
        $path = $this->path($key);
        $content = @file_get_contents($path);

        if ($content === false) {
            return null;
        }

        $data = json_decode($content);

        if (!($data instanceof \stdClass)) {
            return null;
        }

        if ($data->expiry !== null && $data->expiry < time()) {
            @unlink($path);
            return null;
        }

        return ['value' => $data->value, 'expiry' => $data->expiry];
    }

    private function path(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . $key . '.cache';
    }

    private function resolveExpiry(null|int|DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if (is_int($ttl)) {
            return time() + $ttl;
        }

        return (new DateTime())->add($ttl)->getTimestamp();
    }
}
