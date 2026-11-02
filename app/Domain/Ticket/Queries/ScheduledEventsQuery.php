<?php

namespace App\Domain\Ticket\Queries;

use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class ScheduledEventsQuery
{
    public function __construct(
        private ?string $from = null,
        private ?string $to = null,
    ) {}

    /** @return Collection<int, array{id: mixed, title: non-falsy-string, start: string, end: string|null}> */
    public function execute(): Collection
    {
        $query = Ticket::query()
            ->whereNull('deleted_at')
            ->select('id', 'title', 'opened_at', 'resolved_at', 'scheduled_at', 'scheduled_end');

        if ($this->from) {
            $query->where(function ($q) {
                $q->where('scheduled_at', '>=', $this->from)
                    ->orWhere('opened_at', '>=', $this->from)
                    ->orWhere('resolved_at', '>=', $this->from);
            });
        }

        if ($this->to) {
            $query->where(function ($q) {
                $q->where('scheduled_at', '<=', $this->to)
                    ->orWhere('opened_at', '<=', $this->to)
                    ->orWhere('resolved_at', '<=', $this->to);
            });
        }

        return $query->get()->map(function ($ticket) {
            $start = $ticket->scheduled_at ?? $ticket->opened_at ?? $ticket->resolved_at;

            if (! $start) {
                return null;
            }

            return [
                'id' => $ticket->id,
                'title' => '#'.$ticket->id.' - '.$ticket->title,
                'start' => Carbon::parse($start)->toIso8601String(),
                'end' => $ticket->scheduled_end ? Carbon::parse($ticket->scheduled_end)->toIso8601String() : null,
            ];
        })->filter()->values();
    }
}
