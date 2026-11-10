<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEmploymentContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? ($user->isAdmin() || $user->hasPermission('hr.contracts.manage')) : false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'max:10'],
            'work_hours_per_week' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'notes' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedWithDefaults(int $employeeId): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $data['employee_id'] = $employeeId;
        $data['active'] = $this->boolean('active', true);
        $data['salary_currency'] = $data['salary_currency'] ?? 'EUR';

        return $data;
    }
}
