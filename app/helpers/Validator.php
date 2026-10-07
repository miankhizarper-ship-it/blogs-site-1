<?php
declare(strict_types=1);

namespace Helpers;

/**
 * Server-side input validation. Returns list of error strings (empty = valid).
 */
class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, string $label = null): self
    {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (!isset($this->data[$field]) || trim((string) $this->data[$field]) === '') {
            $this->errors[$field] = "$label is required.";
        }
        return $this;
    }

    public function minLen(string $field, int $len, string $label = null): self
    {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (isset($this->data[$field]) && mb_strlen(trim((string) $this->data[$field])) < $len) {
            $this->errors[$field] = "$label must be at least $len characters.";
        }
        return $this;
    }

    public function maxLen(string $field, int $len, string $label = null): self
    {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (isset($this->data[$field]) && mb_strlen((string) $this->data[$field]) > $len) {
            $this->errors[$field] = "$label may not exceed $len characters.";
        }
        return $this;
    }

    public function email(string $field, string $label = 'Email'): self
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "$label is not valid.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label = null): self
    {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = "$label has an invalid value.";
        }
        return $this;
    }

    /** Password policy: 8+ chars, upper, lower, digit. */
    public function strongPassword(string $field): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '' && (
            mb_strlen($v) < 8 ||
            !preg_match('/[A-Z]/', $v) ||
            !preg_match('/[a-z]/', $v) ||
            !preg_match('/\d/', $v)
        )) {
            $this->errors[$field] = 'Password needs 8+ characters with upper, lower and a number.';
        }
        return $this;
    }

    public function matches(string $field, string $other, string $label = 'Confirmation'): self
    {
        if (($this->data[$field] ?? null) !== ($this->data[$other] ?? null)) {
            $this->errors[$field] = "$label does not match.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors ? reset($this->errors) : null;
    }
}
