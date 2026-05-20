<?php

declare(strict_types=1);

namespace Rider\System\Cache;

use DateInterval;
use DateTime;

class ArrayCache implements CacheInterface
{
    private array $store  = [];
    private array $expiry = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->store[$key];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->store[$key]  = $value;
        $this->expiry[$key] = $this->resolveExpiry($ttl);

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key], $this->expiry[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store  = [];
        $this->expiry = [];

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

    public function remember(string $key, null|int|DateInterval $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->store[$key];
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->store)) {
            return false;
        }

        $exp = $this->expiry[$key] ?? null;

        if ($exp !== null && $exp < time()) {
            $this->delete($key);
            return false;
        }

        return true;
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