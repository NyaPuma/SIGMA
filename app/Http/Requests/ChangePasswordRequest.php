<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rule = Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();

        if (! app()->environment('testing')) {
            $rule->uncompromised();
        }

        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'different:current_password', $rule],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => __('auth.palavra-passe atual'),
            'new_password' => __('auth.nova palavra-passe'),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => __('auth.A palavra-passe atual é obrigatória.'),
            'current_password.current_password' => __('auth.A palavra-passe atual está incorreta.'),
            'new_password.required' => __('auth.A nova palavra-passe é obrigatória.'),
            'new_password.different' => __('auth.A nova palavra-passe deve ser diferente da palavra-passe atual.'),
        ];
    }
}
