<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StockMovementTypeEnum;
use App\Models\Equipment;
use App\Models\Part;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'part_id' => ['required', 'integer', Rule::exists(Part::class, 'id')],
            'movement_type' => ['required', Rule::in(StockMovementTypeEnum::values())],
            'quantity' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
            'ticket_id' => ['nullable', 'integer', Rule::exists(Ticket::class, 'id')],
            'equipment_id' => ['nullable', 'integer', Rule::exists(Equipment::class, 'id')],
            'unit_price_snapshot' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function attributes(): array
    {
        return [
            'part_id' => 'part',
            'movement_type' => 'movement type',
            'quantity' => 'quantity',
            'reason' => 'reason',
            'ticket_id' => 'ticket',
            'equipment_id' => 'equipment',
            'unit_price_snapshot' => 'unit price',
        ];
    }
}
