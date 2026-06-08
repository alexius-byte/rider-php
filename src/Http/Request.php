<?php

declare(strict_types=1);

namespace Rider\System\Http;

use stdClass;
use Rider\System\Http\Exception\RequestException;

class Request
{
    private const BODYLESS = ['GET', 'HEAD', 'DELETE', 'OPTIONS'];

    protected readonly string $method;
    protected readonly object $args;
    protected readonly object $query;
    protected readonly object $body;

    public function __construct(array $routeParams)
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->args = (object)$routeParams;
        $this->query = $this->buildQuery();
        $this->body = $this->buildBody();
    }

    private function buildQuery(): object
    {
        $data = filter_input_array(INPUT_GET, FILTER_SANITIZE_SPECIAL_CHARS);
        return (object)(is_array($data) ? $data : []);
    }

    private function buildBody(): object
    {
        if (in_array($this->method, self::BODYLESS, true)) {
            return new stdClass();
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw === false || $raw === '') {
                return new stdClass();
            }
            $decoded = json_decode($raw);
            return ($decoded instanceof stdClass) ? $this->sanitizeObject($decoded) : new stdClass();
        }

        if ($this->method === 'POST') {
            $data = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);
            return (object)(is_array($data) ? $data : []);
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $raw = file_get_contents('php://input');
            if ($raw === false || $raw === '') {
                return new stdClass();
            }
            parse_str($raw, $parsed);
            return (object)$this->sanitizeArray($parsed);
        }

        return new stdClass();
    }

    public function arg(string $key): mixed
    {
        return $this->resolve($this->args, $key, 'route argument');
    }

    public function fullArgs(): object
    {
        return $this->args;
    }

    public function query(string $key): mixed
    {
        return $this->resolve($this->query, $key, 'query parameter');
    }

    public function body(string $key): mixed
    {
        return $this->resolve($this->body, $key, 'body field');
    }

    private function resolve(object $data, string $key, string $context): mixed
    {
        $segments = explode(':', $key);
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_object($current) || !isset($current->$segment)) {
                throw new RequestException('Missing required ' . $context . ': ' . $key);
            }
            $current = $current->$segment;
        }

        return $current;
    }

    private function sanitizeObject(stdClass $obj): stdClass
    {
        $result = new stdClass();
        foreach ((array)$obj as $key => $value) {
            $result->$key = $this->sanitizeValue($value);
        }
        return $result;
    }

    private function sanitizeArray(array $arr): array
    {
        return array_map($this->sanitizeValue(...), $arr);
    }

    private function sanitizeValue(mixed $value): mixed
    {
        return match (true) {
            is_string($value) => filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS),
            is_object($value) => $this->sanitizeObject($value),
            is_array($value) => $this->sanitizeArray($value),
            default => $value,
        };
    }
}
