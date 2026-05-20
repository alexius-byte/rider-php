<?php

declare(strict_types=1);

namespace Rider\System\Validation;

use Rider\System\Validation\Exception\SchemaException;

class Schema
{
    public static function object(array $data, array $rules): array
    {
        $errors = [];
        $result = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = array_values(array_filter(explode('|', $ruleString)));
            $value = $data[$field] ?? null;
            $error = self::applyRules($field, $value, $ruleList);

            if ($error !== null) {
                $errors[$field] = $error;
            } else {
                $names = array_map(fn(string $r): string => explode(':', $r, 2)[0], $ruleList);
                $result[$field] = self::sanitize($value, $names);
            }
        }

        if (!empty($errors)) {
            throw new SchemaException($errors);
        }

        return $result;
    }

    private static function sanitize(mixed $value, array $ruleNames): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array('int', $ruleNames, true)) {
            return (int) $value;
        }

        if (in_array('float', $ruleNames, true)) {
            return (float) $value;
        }

        if (in_array('cpf', $ruleNames, true)) {
            return preg_replace('/\D/', '', trim($value));
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    private static function applyRules(string $field, mixed $value, array $rules): ?string
    {
        $isEmpty = $value === null || $value === '';
        $names = array_map(fn(string $r): string => explode(':', $r, 2)[0], $rules);
        $isOptional = in_array('optional', $names, true) || in_array('nullable', $names, true);

        if ($isEmpty) {
            return $isOptional ? null : "The {$field} field is required";
        }

        foreach ($rules as $rule) {
            $parts = explode(':', $rule, 2);
            $name = $parts[0];
            $param = $parts[1] ?? null;

            if (in_array($name, ['optional', 'nullable'], true)) {
                continue;
            }

            $error = self::check($field, $value, $name, $param);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private static function check(string $field, mixed $value, string $rule, ?string $param): ?string
    {
        return match ($rule) {
            'string' => !is_string($value) ? "The {$field} field must be a string" : null,
            'int' => filter_var($value, FILTER_VALIDATE_INT) === false ? "The {$field} field must be an integer" : null,
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT) === false ? "The {$field} field must be a number" : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? "The {$field} field must be a valid email address" : null,
            'url' => filter_var($value, FILTER_VALIDATE_URL) === false ? "The {$field} field must be a valid URL" : null,
            'min' => self::checkMin($field, $value, $param ?? '0'),
            'max' => self::checkMax($field, $value, $param ?? '0'),
            'in' => self::checkIn($field, $value, $param ?? ''),
            'regex' => preg_match($param ?? '//', (string)$value) !== 1 ? "The {$field} field format is invalid" : null,
            'cpf' => self::checkCpf($field, $value),
            default => null,
        };
    }

    private static function checkMin(string $field, mixed $value, string $param): ?string
    {
        $limit = (float)$param;
        $measured = is_numeric($value) ? (float)$value : mb_strlen((string)$value);
        return $measured < $limit ? "The {$field} field must be at least {$param}" : null;
    }

    private static function checkMax(string $field, mixed $value, string $param): ?string
    {
        $limit = (float)$param;
        $measured = is_numeric($value) ? (float)$value : mb_strlen((string)$value);
        return $measured > $limit ? "The {$field} field must be at most {$param}" : null;
    }

    private static function checkIn(string $field, mixed $value, string $param): ?string
    {
        $options = explode(',', $param);
        return !in_array((string)$value, $options, true) ? "The {$field} field contains an invalid value" : null;
    }

    private static function checkCpf(string $field, mixed $value): ?string
    {
        $raw = (string) $value;

        if (!preg_match('/^\d{11}$|^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $raw)) {
            return "The {$field} field must be a valid CPF";
        }

        $digits = preg_replace('/\D/', '', $raw);

        if (preg_match('/^(\d)\1{10}$/', $digits)) {
            return "The {$field} field must be a valid CPF";
        }

        for ($pass = 0; $pass < 2; $pass++) {
            $sum = 0;
            $length = 9 + $pass;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int)$digits[$i] * ($length + 1 - $i);
            }

            $remainder = $sum % 11;
            $check = $remainder < 2 ? 0 : 11 - $remainder;

            if ((int)$digits[$length] !== $check) {
                return "The {$field} field must be a valid CPF";
            }
        }

        return null;
    }
}
