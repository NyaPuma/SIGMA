<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizes and sanitizes data before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => $this->filled('name') ? trim((string) $this->name) : $this->name,
            ]);
        }

        if ($this->has('email')) {
            $this->merge([
                'email' => $this->filled('email') ? strtolower(trim((string) $this->email)) : $this->email,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User|int|string|null $user */
        $user = $this->route('targetUser') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($user),
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
            ],
            'profile_id' => ['sometimes', 'integer', Rule::exists(UserProfile::class, 'id')],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Friendly attribute names for Laravel error messages.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'password' => 'palavra-passe',
            'profile_id' => 'perfil de utilizador',
            'active' => 'status ativo',
        ];
    }
}
