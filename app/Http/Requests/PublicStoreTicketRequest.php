<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PublicTicketProblemTypeEnum;
use App\Models\Equipment;
use App\Support\PhotoUploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class PublicStoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'integer', Rule::exists(Equipment::class, 'id')],
            'problem_type' => ['required', Rule::in(PublicTicketProblemTypeEnum::values())],
            'description' => ['required', 'string', 'max:5000'],
            'reporter_name' => ['nullable', 'string', 'max:150'],
            'reporter_contact' => ['nullable', 'string', 'max:150'],
            'photo' => [
                'nullable',
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

    public function attributes(): array
    {
        return [
            'equipment_id' => 'equipment',
            'problem_type' => 'problem type',
            'description' => 'description',
            'reporter_name' => 'reporter name',
            'reporter_contact' => 'contact',
            'photo' => 'photo',
        ];
    }
}
