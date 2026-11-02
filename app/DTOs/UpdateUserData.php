<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdateUserData
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $password = null,
        public ?int $profileId = null,
        public ?bool $active = null,
    ) {
        if ($this->name !== null && trim($this->name) === '') {
            throw new \InvalidArgumentException('User name cannot be an empty string.');
        }

        if ($this->email !== null && ! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('The provided e-mail format is invalid.');
        }

        if ($this->profileId !== null && $this->profileId <= 0) {
            throw new \InvalidArgumentException('Profile ID must be a positive integer.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        return new self(
            name: self::parseNullableString($payload['name'] ?? null),
            email: self::parseEmail($payload['email'] ?? null),
            password: self::parsePassword($payload['password'] ?? null),
            profileId: self::parseNullableInt($payload['profile_id'] ?? null),
            active: self::parseNullableBool($payload['active'] ?? null),
        );
    }

    /**
     * Sanitize optional strings, converting "" or whitespace-only values to null.
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
     * Sanitize and lower-case the e-mail value when provided.
     */
    private static function parseEmail(mixed $value): ?string
    {
        $string = self::parseNullableString($value);

        return $string !== null ? mb_strtolower($string) : null;
    }

    /**
     * Convert blank password strings ("") to null; preserves internal whitespace.
     */
    private static function parsePassword(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Sanitize optional integer IDs, converting "", 0, or invalid values to null.
     */
    private static function parseNullableInt(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return $parsed && $parsed > 0 ? $parsed : null;
    }

    /**
     * Normalize boolean input without forcing a default — null input stays null.
     */
    private static function parseNullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    /**
     * Returns fields provided for Eloquent update; password is excluded
     * (handled separately via hashing in the service layer).
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'profile_id' => $this->profileId,
            'active' => $this->active,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function hasUpdates(): bool
    {
        return $this->hasPassword() || ! empty($this->toArray());
    }
}
