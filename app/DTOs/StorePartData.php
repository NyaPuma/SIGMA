<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * Creation data for a spare part, sanitized before reaching the Action layer.
 */
final readonly class StorePartData
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
        public int $currentStock,
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

        $currentStock = isset($data['current_stock']) && is_numeric($data['current_stock']) ? (int) $data['current_stock'] : 0;

        if ($currentStock < 0) {
            throw new InvalidArgumentException('Initial stock cannot be negative.');
        }

        $sku = isset($data['sku']) && is_string($data['sku']) ? trim($data['sku']) : '';
        $name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : '';
        $description = isset($data['description']) && is_string($data['description']) ? trim($data['description']) : null;
        $brand = isset($data['brand']) && is_string($data['brand']) ? trim($data['brand']) : null;
        $manufacturerRef = isset($data['manufacturer_ref']) && is_string($data['manufacturer_ref']) ? trim($data['manufacturer_ref']) : null;
        $partCategoryId = isset($data['part_category_id']) && is_numeric($data['part_category_id']) ? (int) $data['part_category_id'] : null;
        $unitOfMeasure = isset($data['unit_of_measure']) && is_string($data['unit_of_measure']) ? $data['unit_of_measure'] : 'unit';
        $costPrice = isset($data['cost_price']) && is_numeric($data['cost_price']) ? (float) $data['cost_price'] : 0.0;
        $taxRateId = isset($data['tax_rate_id']) && is_numeric($data['tax_rate_id']) ? (int) $data['tax_rate_id'] : null;
        $salePrice = isset($data['sale_price']) && $data['sale_price'] !== '' && is_numeric($data['sale_price']) ? (float) $data['sale_price'] : null;
        $minStock = isset($data['min_stock']) && is_numeric($data['min_stock']) ? (int) $data['min_stock'] : 0;
        $maxStock = isset($data['max_stock']) && $data['max_stock'] !== '' && is_numeric($data['max_stock']) ? (int) $data['max_stock'] : null;
        $location = isset($data['location']) && is_string($data['location']) ? trim($data['location']) : null;
        $photo = isset($data['photo']) && is_string($data['photo']) ? trim($data['photo']) : null;
        $active = (bool) ($data['active'] ?? true);
        $technicalNotes = isset($data['technical_notes']) && is_string($data['technical_notes']) ? trim($data['technical_notes']) : null;

        return new self(
            sku: strtoupper(trim($sku)),
            name: trim($name),
            description: $description,
            brand: $brand,
            manufacturerRef: $manufacturerRef,
            partCategoryId: $partCategoryId,
            unitOfMeasure: trim($unitOfMeasure),
            costPrice: $costPrice,
            taxRateId: $taxRateId,
            salePrice: $salePrice,
            currentStock: $currentStock,
            minStock: $minStock,
            maxStock: $maxStock,
            location: $location,
            photo: $photo,
            active: $active,
            technicalNotes: $technicalNotes,
        );
    }
}
