<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePreventiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trims extra whitespace from text fields before validation.
     */
    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $description = $this->input('description');

        $this->merge([
            'title' => $this->filled('title') && is_scalar($title) ? trim((string) $title) : $title,
            'description' => $this->filled('description') && is_scalar($description) ? trim((string) $description) : $description,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => [
                'required',
                'date',
                'date_format:Y-m-d\TH:i,Y-m-d H:i:s,Y-m-d',
                'after_or_equal:today',
            ],
            'technician_id' => [
                'nullable',
                'integer',
                Rule::exists(User::class, 'id'),
            ],
        ];
    }

    /**
     * Friendly attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'title' => 'preventive title',
            'description' => 'description',
            'scheduled_at' => 'scheduled date',
            'technician_id' => 'responsible technician',
        ];
    }
}
