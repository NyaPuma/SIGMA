<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\PhotoUploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class UploadPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                File::image()
                    ->types(PhotoUploadPolicy::allowedTypes())
                    ->max(PhotoUploadPolicy::maxSizeKb())
                    ->dimensions(
                        Rule::dimensions()
                            ->maxWidth(PhotoUploadPolicy::maxWidth())
                            ->maxHeight(PhotoUploadPolicy::maxHeight())
                    ),
            ],
        ];
    }

    /**
     * Nomes amigáveis dos atributos para as mensagens de erro do Laravel.
     */
    public function attributes(): array
    {
        return [
            'photo' => 'photo',
        ];
    }
}
