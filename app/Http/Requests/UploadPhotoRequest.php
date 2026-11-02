<?php

declare(strict_types=1);

namespace App\Http\Requests;

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
        $configuredMimes = config('services.upload.allowed_photo_mimes', ['jpeg', 'jpg', 'png', 'gif', 'webp']);
        $allowedMimes = is_array($configuredMimes)
            ? array_values(array_filter($configuredMimes, 'is_string'))
            : [];

        if ($allowedMimes === []) {
            $allowedMimes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
        }

        $configuredMaxSize = config('services.upload.max_photo_size_kb', 2048);
        $maxPhotoSizeKb = is_numeric($configuredMaxSize) ? (int) $configuredMaxSize : 2048;

        return [
            'photo' => [
                'required',
                File::image()
                    ->types($allowedMimes)
                    ->max($maxPhotoSizeKb)
                    ->dimensions(
                        Rule::dimensions()
                            ->maxWidth(4096)
                            ->maxHeight(4096)
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
