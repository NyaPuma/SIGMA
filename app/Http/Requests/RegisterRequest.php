<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize input data before validation rules run.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->name) : $this->name,
            'email' => $this->filled('email') ? strtolower(trim((string) $this->email)) : $this->email,
        ]);
    }

    public static function passwordRules(): array
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
            'required',
            'confirmed',
            $rule,
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'password' => self::passwordRules(),
        ];
    }
}
