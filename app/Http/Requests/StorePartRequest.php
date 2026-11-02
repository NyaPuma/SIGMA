<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PartUnitOfMeasureEnum;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\TaxRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sku = $this->input('sku');
        $name = $this->input('name');

        $this->merge([
            'sku' => $this->filled('sku') && is_scalar($sku) ? trim((string) $sku) : $sku,
            'name' => $this->filled('name') && is_scalar($name) ? trim((string) $name) : $name,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100', Rule::unique(Part::class, 'sku')],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:100'],
            'manufacturer_ref' => ['nullable', 'string', 'max:100'],
            'part_category_id' => ['nullable', 'integer', Rule::exists(PartCategory::class, 'id')],
            'unit_of_measure' => ['required', Rule::in(PartUnitOfMeasureEnum::values())],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'tax_rate_id' => ['nullable', 'integer', Rule::exists(TaxRate::class, 'id')],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'gte:min_stock'],
            'location' => ['nullable', 'string', 'max:150'],
            'photo' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'technical_notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sku' => 'code (SKU)',
            'name' => 'name',
            'description' => 'description',
            'brand' => 'brand',
            'manufacturer_ref' => 'manufacturer reference',
            'part_category_id' => 'category',
            'unit_of_measure' => 'unit of measure',
            'cost_price' => 'cost price',
            'tax_rate_id' => 'VAT rate',
            'sale_price' => 'sale price',
            'current_stock' => 'current stock',
            'min_stock' => 'minimum stock',
            'max_stock' => 'maximum stock',
            'location' => 'location',
            'photo' => 'photo',
            'active' => 'active',
            'technical_notes' => 'technical notes',
        ];
    }
}
