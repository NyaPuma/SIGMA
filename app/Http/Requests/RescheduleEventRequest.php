<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RescheduleEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'start' => 'start',
            'end' => 'end',
        ];
    }
}
