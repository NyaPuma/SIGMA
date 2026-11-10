<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? ($user->isAdmin() || $user->hasPermission('hr.leaves.manage')) : false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'days' => ['required', 'numeric', 'min:0.5'],
            'reason' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedWithDefaults(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $data['status'] = 'pending';

        return $data;
    }
}
