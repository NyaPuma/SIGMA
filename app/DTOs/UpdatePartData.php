<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdatePartData
{
    public function __construct(
        public string $sku,
        public string $name,
        public ?string $description,
        public ?string $brand,
        public ?string $manufacturerRef,
        public ?int $partCategoryId,
        public string $unitOfMeasure,
        public float $costPrice,
        public ?int $taxRateId,
        public ?float $salePrice,
        public int $minStock,
        public ?int $maxStock,
        public ?string $location,
        public ?string $photo,
        public bool $active,
        public ?string $technicalNotes,
    ) {}

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        if ($data instanceof FormRequest) {
            $data = $data->validated();
        }

        return new self(
            sku: strtoupper(trim((string) $data['sku'])),
            name: trim((string) $data['name']),
            description: isset($data['description']) ? trim((string) $data['description']) : null,
            brand: isset($data['brand']) ? trim((string) $data['brand']) : null,
            manufacturerRef: isset($data['manufacturer_ref']) ? trim((string) $data['manufacturer_ref']) : null,
            partCategoryId: isset($data['part_category_id']) ? (int) $data['part_category_id'] : null,
            unitOfMeasure: (string) ($data['unit_of_measure'] ?? 'unit'),
            costPrice: (float) $data['cost_price'],
            taxRateId: isset($data['tax_rate_id']) ? (int) $data['tax_rate_id'] : null,
            salePrice: isset($data['sale_price']) && $data['sale_price'] !== '' ? (float) $data['sale_price'] : null,
            minStock: (int) ($data['min_stock'] ?? 0),
            maxStock: isset($data['max_stock']) && $data['max_stock'] !== '' ? (int) $data['max_stock'] : null,
            location: isset($data['location']) ? trim((string) $data['location']) : null,
            photo: isset($data['photo']) ? trim((string) $data['photo']) : null,
            active: (bool) ($data['active'] ?? true),
            technicalNotes: isset($data['technical_notes']) ? trim((string) $data['technical_notes']) : null,
        );
    }
}
