<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Example: If comments belong to a route-bound model:
        // $ticket = $this->route('ticket');
        // return $ticket !== null && $this->user()?->can('comment', $ticket) === true;

        return true;
    }

    /**
     * Trim and clean up whitespace prior to validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('comment')) {
            $this->merge([
                'comment' => trim(is_string($this->comment) ? $this->comment : ''),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
