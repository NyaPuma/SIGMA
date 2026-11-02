<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class StoreUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?int $profileId = null,
        public bool $active = true,
    ) {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('User name cannot be empty.');
        }

        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('The provided e-mail is invalid.');
        }

        if ($this->password === '') {
            throw new \InvalidArgumentException('Password cannot be empty.');
        }

        if ($this->profileId !== null && $this->profileId <= 0) {
            throw new \InvalidArgumentException('Profile ID must be a positive integer.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        $rawName = $payload['name'] ?? '';
        $rawEmail = $payload['email'] ?? '';
        $rawPassword = $payload['password'] ?? '';

        return new self(
            name: trim(is_scalar($rawName) ? (string) $rawName : ''),
            email: mb_strtolower(trim(is_scalar($rawEmail) ? (string) $rawEmail : '')),
            password: is_scalar($rawPassword) ? (string) $rawPassword : '',
            profileId: self::parseNullableInt($payload['profile_id'] ?? null),
            active: self::parseBool($payload['active'] ?? true),
        );
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
     * Normalize boolean input — handles "true", "false", "1", "0", "on", etc.
     * Defaults to true when null is passed.
     */
    private static function parseBool(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'profile_id' => $this->profileId,
            'active' => $this->active,
        ];
    }
}
