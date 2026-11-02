<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PartCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePartCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(PartCategory::class, 'name')->ignore($this->route('category'))],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'active' => 'ativo',
        ];
    }
}
