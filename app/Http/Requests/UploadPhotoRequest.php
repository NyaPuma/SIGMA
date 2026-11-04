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
        $configuredMimes = config('services.custom.upload.allowed_photo_mimes', []);
        $allowedMimes = is_array($configuredMimes)
            ? array_values(array_filter($configuredMimes, 'is_string'))
            : [];

        if ($allowedMimes === []) {
            $allowedMimes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
        }

        $maxPhotoSizeKb = $this->positiveIntConfig('services.custom.upload.max_photo_size_kb', 2048);
        $maxPhotoWidth = $this->positiveIntConfig('services.custom.upload.max_photo_width', 4096);
        $maxPhotoHeight = $this->positiveIntConfig('services.custom.upload.max_photo_height', 4096);

        return [
            'photo' => [
                'required',
                File::image()
                    ->types($allowedMimes)
                    ->max($maxPhotoSizeKb)
                    ->dimensions(
                        Rule::dimensions()
                            ->maxWidth($maxPhotoWidth)
                            ->maxHeight($maxPhotoHeight)
                    ),
            ],
        ];
    }

    /**
     * Reads a size limit that must stay usable when the environment value is empty or bogus.
     */
    private function positiveIntConfig(string $key, int $fallback): int
    {
        $configured = config($key);

        return is_numeric($configured) && (int) $configured > 0 ? (int) $configured : $fallback;
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
