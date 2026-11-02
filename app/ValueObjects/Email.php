<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable Value Object representing a validated e-mail address.
 */
final readonly class Email
{
    private string $value;

    public function __construct(string $email)
    {
        $this->validate($email);
        $this->value = strtolower(trim($email));
    }

    private function validate(string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format: {$email}");
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function domain(): string
    {
        return substr(strrchr($this->value, '@') ?: '', 1);
    }

    public function localPart(): string
    {
        return strstr($this->value, '@', true) ?: '';
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
