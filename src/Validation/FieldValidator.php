<?php

declare(strict_types=1);

namespace Rider\System\Validation;


class FieldValidator
{
    private bool $nullable = false;
    private bool $isEmpty;
    private bool $lastRuleFailed = false;

    public function __construct(
        private readonly Validator $validator,
        private readonly string    $field,
        private readonly mixed     $value
    )
    {
        $this->isEmpty = $value === null || $value === '';
    }

    public function field(string $name): FieldValidator
    {
        return $this->validator->field($name);
    }

    public function passes(): bool
    {
        return $this->validator->passes();
    }

    public function errors(): array
    {
        return $this->validator->errors();
    }

    public function validate(): void
    {
        $this->validator->validate();
    }

    public function nullable(): static
    {
        $this->lastRuleFailed = false;
        $this->nullable = true;

        if ($this->isEmpty) {
            $this->validator->removeError($this->field);
        }

        return $this;
    }

    public function required(): static
    {
        $this->lastRuleFailed = false;

        if ($this->isEmpty) {
            $this->fail("The {$this->field} field is required");
        }

        return $this;
    }

    public function string(): static
    {
        $this->lastRuleFailed = false;

        if ($this->skip()) {
            return $this;
        }

        if (!is_string($this->value)) {
            $this->fail("The {$this->field} field must be a string");
        }

        return $this;
    }

    public function int(): static
    {
        $this->lastRuleFailed = false;

        if ($this->skip()) {
            return $this;
        }

        if (filter_var($this->value, FILTER_VALIDATE_INT) === false) {
            $this->fail("The {$this->field} field must be an integer");
        }

        return $this;
    }

    public function float(): static
    {
        $this->lastRuleFailed = false;

        if ($this->skip()) {
            return $this;
        }

        if (filter_var($this->value, FILTER_VALIDATE_FLOAT) === false) {
            $this->fail("The {$this->field} field must be a number");
        }

        return $this;
    }

    public function email(): static
    {
        $this->lastRuleFailed = false;

        if ($this->skip()) {
            return $this;
        }

        if (filter_var($this->value, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail("The {$this->field} field must be a valid email address");
        }

        return $this;
    }

    public function url(): static
    {
        $this->lastRuleFailed = false;

        if ($this->skip()) {
            return $this;
        }

        if (filter_var($this->value, FILTER_VALIDATE_URL) === false) {
            $this->fail("The {$this->field} field must be a valid URL");
        }

        return $this;
    }

    public function min(int|float $limit): static
    {
        $this->lastRuleFailed = false;

        if ($this->skip()) {
            return $this;
        }

        $measured = is_numeric($this->value) ? (float)$this->value : mb_strlen((string)$this->value);

        if ($measured < $limit) {
            $this->fail("The {$this->field} field must be at least {$limit}");
        }

        return $this;
    }

    public function max(int|float $limit): static
    {
        $this->lastRuleFailed = false;

        if ($this->skip()) {
            return $this;
        }

        $measured = is_numeric($this->value) ? (float)$this->value : mb_strlen((string)$this->value);

        if ($measured > $limit) {
            $this->fail("The {$this->field} field must be at most {$limit}");
        }

        return $this;
    }

    public function in(array $options): static
    {
        $this->lastRuleFailed = false;

        if ($this->skip()) {
            return $this;
        }

        if (!in_array($this->value, $options, strict: true)) {
            $this->fail("The {$this->field} field contains an invalid value");
        }

        return $this;
    }

    public function regex(string $pattern): static
    {
        $this->lastRuleFailed = false;

        if ($this->skip()) {
            return $this;
        }

        if (preg_match($pattern, (string)$this->value) !== 1) {
            $this->fail("The {$this->field} field format is invalid");
        }

        return $this;
    }

    public function cpf(): static
    {
        $this->lastRuleFailed = false;

        if ($this->skip()) {
            return $this;
        }

        $digits = preg_replace('/\D/', '', (string)$this->value);

        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            $this->fail("The {$this->field} field must be a valid CPF");
            return $this;
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
                $this->fail("The {$this->field} field must be a valid CPF");
                return $this;
            }
        }

        return $this;
    }

    public function message(string $message): static
    {
        if ($this->lastRuleFailed) {
            $this->validator->setError($this->field, $message);
            $this->lastRuleFailed = false;
        }

        return $this;
    }

    private function skip(): bool
    {
        return $this->nullable && $this->isEmpty;
    }

    private function fail(string $message): void
    {
        $this->lastRuleFailed = true;
        $this->validator->addError($this->field, $message);
    }
}
