<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

final class StartTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Ticket|null $ticket */
        $ticket = $this->route('ticket');

        // Optional: Perform policy check directly in the request
        return $ticket !== null && $this->user()?->can('update', $ticket) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'force' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Type-safe getter for controller usage.
     */
    public function isForced(): bool
    {
        return $this->boolean('force');
    }
}
