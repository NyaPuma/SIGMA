<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class ProfileUpdateData
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
    ) {
        if ($this->email !== null && ! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('The provided e-mail format is invalid.');
        }

        if ($this->name !== null && trim($this->name) === '') {
            throw new \InvalidArgumentException('Name cannot consist of whitespace only.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        return new self(
            name: self::parseNullableString($payload['name'] ?? null),
            email: self::parseEmail($payload['email'] ?? null),
        );
    }

    /**
     * Trim whitespace and convert empty strings to null.
     */
    private static function parseNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Sanitize and lower-case the e-mail value.
     */
    private static function parseEmail(mixed $value): ?string
    {
        $string = self::parseNullableString($value);

        return $string !== null ? mb_strtolower($string) : null;
    }

    /**
     * Returns only the fields that were actually provided, preventing accidental Eloquent overwrites.
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function hasChanges(): bool
    {
        return $this->name !== null || $this->email !== null;
    }
}
