<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class PasswordChangeData
{
    public function __construct(
        public string $currentPassword,
        public string $newPassword,
    ) {
        if ($this->currentPassword === '') {
            throw new \InvalidArgumentException('Current password is required.');
        }

        if ($this->newPassword === '') {
            throw new \InvalidArgumentException('New password is required.');
        }

        if ($this->currentPassword === $this->newPassword) {
            throw new \InvalidArgumentException('New password must differ from the current password.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        return new self(
            currentPassword: (string) ($payload['current_password'] ?? ''),
            newPassword: (string) ($payload['new_password'] ?? ''),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'current_password' => $this->currentPassword,
            'new_password' => $this->newPassword,
        ];
    }
}
