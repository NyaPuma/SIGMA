<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class RequestBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'budget_amount' => ['required', 'numeric', 'min:0.01'],
            'budget_details' => ['nullable', 'array', 'min:1'],
            'budget_details.*.description' => ['required', 'string', 'max:255'],
            'budget_details.*.quantity' => ['required', 'integer', 'min:1'],
            'budget_details.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Custom validation hook to ensure line items sum up to budget_amount.
     */
    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $details = $this->input('budget_details');

                if (! is_array($details) || empty($details)) {
                    return;
                }

                $calculatedTotal = array_reduce(
                    $details,
                    fn (float $acc, array $item) => $acc + ((int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0)),
                    0.0
                );

                if (abs($calculatedTotal - (float) $this->input('budget_amount')) > 0.01) {
                    $validator->errors()->add(
                        'budget_amount',
                        'The total budget amount does not match the sum of the line item details.'
                    );
                }
            },
        ];
    }

    /**
     * User-friendly field names for nested array validation errors.
     */
    public function attributes(): array
    {
        return [
            'budget_details.*.description' => 'item description',
            'budget_details.*.quantity' => 'item quantity',
            'budget_details.*.unit_price' => 'item unit price',
        ];
    }
}
