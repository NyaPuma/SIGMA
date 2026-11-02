<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdateSupplierData
{
    public function __construct(
        public string $name,
        public ?string $nif,
        public ?string $contact,
        public ?string $email,
        public ?string $address,
        public ?int $avgLeadTimeDays,
    ) {}

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        if ($data instanceof FormRequest) {
            $data = $data->validated();
        }

        return new self(
            name: self::string($data['name'] ?? ''),
            nif: isset($data['nif']) ? self::string($data['nif']) : null,
            contact: isset($data['contact']) ? self::string($data['contact']) : null,
            email: isset($data['email']) ? self::string($data['email']) : null,
            address: isset($data['address']) ? self::string($data['address']) : null,
            avgLeadTimeDays: isset($data['avg_lead_time_days']) && is_numeric($data['avg_lead_time_days'])
                ? (int) $data['avg_lead_time_days']
                : null,
        );
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
