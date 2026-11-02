<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SubmitBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estimated_budget' => ['required', 'numeric', 'min:0.01'],
            'budget_details' => ['nullable', 'array', 'min:1'],
            'budget_details.*.description' => ['required', 'string', 'max:255'],
            'budget_details.*.type' => ['required', 'string', Rule::in(['material', 'labor'])],

            // Conditional validation for 'material' type items
            'budget_details.*.quantity' => ['required_if:budget_details.*.type,material', 'nullable', 'numeric', 'min:0.01'],
            'budget_details.*.unit_price' => ['required_if:budget_details.*.type,material', 'nullable', 'numeric', 'min:0'],

            // Conditional validation for 'labor' type items
            'budget_details.*.hours' => ['required_if:budget_details.*.type,labor', 'nullable', 'numeric', 'min:0.1'],
            'budget_details.*.hourly_rate' => ['required_if:budget_details.*.type,labor', 'nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Validates that the sum of individual items matches the estimated total.
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
                    function (float $acc, array $item) {
                        $type = $item['type'] ?? null;

                        if ($type === 'material') {
                            return $acc + ((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0));
                        }

                        if ($type === 'labor') {
                            return $acc + ((float) ($item['hours'] ?? 0) * (float) ($item['hourly_rate'] ?? 0));
                        }

                        return $acc;
                    },
                    0.0
                );

                $estimatedBudget = (float) ($this->input('estimated_budget') ?? $this->input('estimatedBudget'));

                if (abs($calculatedTotal - $estimatedBudget) > 0.01) {
                    $validator->errors()->add(
                        'estimated_budget',
                        'The estimated total does not match the sum of the budget details.'
                    );
                }
            },
        ];
    }

    /**
     * Maps attributes to human-readable error messages.
     */
    public function attributes(): array
    {
        return [
            'estimated_budget' => 'estimated budget',
            'estimatedBudget' => 'estimated budget',
            'budget_details' => 'budget details',
            'budget_details.*.description' => 'item description',
            'budget_details.*.type' => 'item type',
            'budget_details.*.quantity' => 'quantity',
            'budget_details.*.unit_price' => 'unit price',
            'budget_details.*.hours' => 'labour hours',
            'budget_details.*.hourly_rate' => 'hourly rate',
        ];
    }
}
