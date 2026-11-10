<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? ($user->isAdmin() || $user->hasPermission('hr.attendance.manage')) : false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after_or_equal:check_in'],
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:present,absent,late,half_day,remote,holiday'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedWithDefaults(?int $recordedBy): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $break = $data['break_minutes'] ?? 0;
        $data['break_minutes'] = is_numeric($break) ? (int) $break : 0;
        $data['recorded_by'] = $recordedBy;

        return $data;
    }
}
