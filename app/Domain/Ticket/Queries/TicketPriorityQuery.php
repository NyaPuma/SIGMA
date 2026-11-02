<?php

namespace App\Domain\Ticket\Queries;

use App\Enums\TicketPriorityEnum;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final readonly class TicketPriorityQuery
{
    /**
     * @param  Builder<Ticket>  $baseQuery
     */
    public function __construct(
        private Builder $baseQuery,
    ) {}

    /** @return array<string, int> */
    public function execute(): array
    {
        // Clone to avoid unintended mutation of the original $baseQuery
        $row = (clone $this->baseQuery)
            ->selectRaw('
                SUM(CASE WHEN priority = ? THEN 1 ELSE 0 END) as low,
                SUM(CASE WHEN priority = ? THEN 1 ELSE 0 END) as medium,
                SUM(CASE WHEN priority IN (?, ?) THEN 1 ELSE 0 END) as high
            ', [
                TicketPriorityEnum::Low->value,
                TicketPriorityEnum::Medium->value,
                TicketPriorityEnum::High->value,
                TicketPriorityEnum::Critical->value,
            ])
            ->first();

        $low = $row !== null ? ($row->low ?? null) : null;
        $medium = $row !== null ? ($row->medium ?? null) : null;
        $high = $row !== null ? ($row->high ?? null) : null;

        return [
            'low' => is_numeric($low) ? (int) $low : 0,
            'medium' => is_numeric($medium) ? (int) $medium : 0,
            'high' => is_numeric($high) ? (int) $high : 0,
        ];
    }
}
