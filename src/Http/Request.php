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
        if (!isset($this->args->$key)) {
            throw new RequestException('Missing required route argument: ' . $key);
        }
        return $this->args->$key;
    }

    public function query(string $key): mixed
    {
        if (!isset($this->query->$key)) {
            throw new RequestException('Missing required query parameter: ' . $key);
        }
        return $this->query->$key;
    }

    public function body(string $key): mixed
    {
        if (!isset($this->body->$key)) {
            throw new RequestException('Missing required body field: ' . $key);
        }
        return $this->body->$key;
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
