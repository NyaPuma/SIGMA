<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trims whitespace from submitted data.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => $this->filled('name') ? trim(is_string($this->name) ? $this->name : '') : $this->name,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'current_password' => [
                'required_with:password',
                'nullable',
                'string',
                'current_password',
            ],
            'password' => [
                'nullable',
                'string',
                (static function () {
                    $rule = Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols();
                    if (! app()->environment('testing')) {
                        $rule->uncompromised();
                    }

                    return $rule;
                })(),
                'confirmed',
            ],
        ];
    }

    /**
     * Friendly attribute names for Laravel's error messages.
     */
    public function attributes(): array
    {
        return [
            'name' => 'name',
            'current_password' => 'current password',
            'password' => 'new password',
            'password_confirmation' => 'new password confirmation',
        ];
    }
}
