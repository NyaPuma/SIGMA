<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? ($user->isAdmin() || $user->hasPermission('hr.departments.manage')) : false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $department = $this->route('department');
        $departmentId = $department instanceof Department ? $department->getKey() : $department;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($departmentId)],
            'description' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedWithDefaults(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $data['active'] = $this->boolean('active');

        return $data;
    }
}
