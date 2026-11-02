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

        $sku = $data['sku'] ?? '';
        $name = $data['name'] ?? '';
        $unitOfMeasure = $data['unit_of_measure'] ?? 'unit';
        $costPrice = $data['cost_price'] ?? 0;
        $minStock = $data['min_stock'] ?? 0;

        return new self(
            sku: strtoupper(trim(is_scalar($sku) ? (string) $sku : '')),
            name: trim(is_scalar($name) ? (string) $name : ''),
            description: self::nullableString($data['description'] ?? null),
            brand: self::nullableString($data['brand'] ?? null),
            manufacturerRef: self::nullableString($data['manufacturer_ref'] ?? null),
            partCategoryId: self::nullableInt($data['part_category_id'] ?? null),
            unitOfMeasure: is_scalar($unitOfMeasure) ? (string) $unitOfMeasure : 'unit',
            costPrice: is_numeric($costPrice) ? (float) $costPrice : 0.0,
            taxRateId: self::nullableInt($data['tax_rate_id'] ?? null),
            salePrice: self::nullableFloat($data['sale_price'] ?? null),
            minStock: is_numeric($minStock) ? (int) $minStock : 0,
            maxStock: self::nullableInt($data['max_stock'] ?? null),
            location: self::nullableString($data['location'] ?? null),
            photo: self::nullableString($data['photo'] ?? null),
            active: self::parseBool($data['active'] ?? true),
            technicalNotes: self::nullableString($data['technical_notes'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? trim((string) $value) : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return is_scalar($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : true;
    }
}
