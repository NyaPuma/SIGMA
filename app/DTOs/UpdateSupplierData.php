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
            name: trim((string) $data['name']),
            nif: isset($data['nif']) ? trim((string) $data['nif']) : null,
            contact: isset($data['contact']) ? trim((string) $data['contact']) : null,
            email: isset($data['email']) ? trim((string) $data['email']) : null,
            address: isset($data['address']) ? trim((string) $data['address']) : null,
            avgLeadTimeDays: isset($data['avg_lead_time_days']) ? (int) $data['avg_lead_time_days'] : null,
        );
    }
}
