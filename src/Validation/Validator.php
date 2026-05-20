<?php

declare(strict_types=1);

namespace Rider\System\Validation;

use Rider\System\Validation\Exception\ValidationException;

class Validator
{
    private array $errors = [];

    private function __construct(private readonly array $data) {}

    public static function make(array $data): static
    {
        return new static($data);
    }

    public function field(string $name): FieldValidator
    {
        return new FieldValidator($this, $name, $this->data[$name] ?? null);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validate(): void
    {
        if (!$this->passes()) {
            throw new ValidationException($this->errors);
        }
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function setError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function removeError(string $field): void
    {
        unset($this->errors[$field]);
    }
}
