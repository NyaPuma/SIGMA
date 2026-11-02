<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize email input before running token or password validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim(is_string($this->email) ? $this->email : '')),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'lowercase', 'email'],
            'password' => [
                'required',
                'confirmed',
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
            ],
        ];
    }
}
