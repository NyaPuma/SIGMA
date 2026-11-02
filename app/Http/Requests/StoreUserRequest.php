<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trims extra whitespace and normalizes email before validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $email = $this->input('email');

        $this->merge([
            'name' => $this->filled('name') && is_scalar($name) ? trim((string) $name) : $name,
            'email' => $this->filled('email') && is_scalar($email) ? strtolower(trim((string) $email)) : $email,
        ]);
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
            'password' => [
                'required',
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
            ],
            'profile_id' => [
                'required',
                'integer',
                Rule::exists(UserProfile::class, 'id'),
            ],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Maps attribute names to Laravel's error messages.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'email',
            'password' => 'palavra-passe',
            'profile_id' => 'perfil',
            'active' => 'status ativo',
        ];
    }
}
