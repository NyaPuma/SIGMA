<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Part */
final class PartResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'brand' => $this->brand,
            'manufacturer_ref' => $this->manufacturer_ref,
            'unit_of_measure' => $this->unit_of_measure,
            'cost_price' => $this->cost_price,
            'price_with_vat' => $this->priceWithVat(),
            'sale_price' => $this->sale_price,
            'tax_rate_id' => $this->tax_rate_id,
            'tax_rate' => $this->whenLoaded('taxRate', fn () => new TaxRateResource($this->taxRate)),
            'category' => $this->whenLoaded('category', fn () => new PartCategoryResource($this->category)),
            'suppliers' => $this->whenLoaded('suppliers', fn () => SupplierResource::collection($this->suppliers)),
            'current_stock' => $this->current_stock,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'is_low_stock' => $this->isLowStock(),
            'stock_value' => $this->stockValue(),
            'location' => $this->location,
            'photo' => $this->photo,
            'active' => $this->active,
            'technical_notes' => $this->technical_notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
