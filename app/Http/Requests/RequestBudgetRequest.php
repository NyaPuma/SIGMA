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
                    fn (float $acc, mixed $item): float => $acc + self::lineTotal($item),
                    0.0
                );

                $budgetAmount = $this->input('budget_amount');
                $budgetAmount = is_numeric($budgetAmount) ? (float) $budgetAmount : 0.0;

                if (abs($calculatedTotal - $budgetAmount) > 0.01) {
                    $validator->errors()->add(
                        'budget_amount',
                        'The total budget amount does not match the sum of the line item details.'
                    );
                }
            },
        ];
    }

    private static function lineTotal(mixed $item): float
    {
        if (! is_array($item)) {
            return 0.0;
        }

        $quantity = $item['quantity'] ?? 0;
        $unitPrice = $item['unit_price'] ?? 0;

        $quantity = is_numeric($quantity) ? (int) $quantity : 0;
        $unitPrice = is_numeric($unitPrice) ? (float) $unitPrice : 0.0;

        return $quantity * $unitPrice;
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
