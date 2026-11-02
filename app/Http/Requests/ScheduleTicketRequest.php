<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ScheduleTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scheduled_at' => [
                'required',
                'date',
                // Matches HTML5 datetime-local ("2026-07-30T10:00") or standard DB datetime ("2026-07-30 10:00:00")
                'date_format:Y-m-d\TH:i,Y-m-d H:i:s',
                'after_or_equal:now',
            ],
            'scheduled_end' => [
                'nullable',
                'date',
                'date_format:Y-m-d\TH:i,Y-m-d H:i:s',
                'after:scheduled_at',
            ],
        ];
    }
}
