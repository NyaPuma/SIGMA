<?php

namespace App\Domain\Ticket\Queries;

use App\Enums\BudgetStatusEnum;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class TicketKpiQuery
{
    public function __construct(
        private int $openStatusId,
        private int $inProgressStatusId,
        private int $closedStatusId,
        private int $slaTargetMinutes = 480,
    ) {}

    /** @return array{open_tickets: int, in_progress_tickets: int, budget_pending_tickets: int, closed_tickets: int, avg_resolution: float, avg_waiting: float, sla_met: int} */
    public function execute(): array
    {
        $baseQuery = Ticket::query()->whereNull('tickets.deleted_at');

        $kpiRow = $this->buildKpiQuery($baseQuery)->first();

        $openTickets = $kpiRow !== null ? ($kpiRow->open_tickets ?? null) : null;
        $inProgressTickets = $kpiRow !== null ? ($kpiRow->in_progress_tickets ?? null) : null;
        $budgetPendingTickets = $kpiRow !== null ? ($kpiRow->budget_pending_tickets ?? null) : null;
        $closedTickets = $kpiRow !== null ? ($kpiRow->closed_tickets ?? null) : null;
        $avgResolution = $kpiRow !== null ? ($kpiRow->avg_resolution ?? null) : null;
        $avgWaiting = $kpiRow !== null ? ($kpiRow->avg_waiting ?? null) : null;
        $slaMet = $kpiRow !== null ? ($kpiRow->sla_met ?? null) : null;

        return [
            'open_tickets' => is_numeric($openTickets) ? (int) $openTickets : 0,
            'in_progress_tickets' => is_numeric($inProgressTickets) ? (int) $inProgressTickets : 0,
            'budget_pending_tickets' => is_numeric($budgetPendingTickets) ? (int) $budgetPendingTickets : 0,
            'closed_tickets' => is_numeric($closedTickets) ? (int) $closedTickets : 0,
            'avg_resolution' => is_numeric($avgResolution) ? (float) $avgResolution : 0.0,
            'avg_waiting' => is_numeric($avgWaiting) ? (float) $avgWaiting : 0.0,
            'sla_met' => is_numeric($slaMet) ? (int) $slaMet : 0,
        ];
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    private function buildKpiQuery(Builder $query): Builder
    {
        $diffExpr = $this->diffMinutesExpression('opened_at', 'closed_at');
        $diffWaitExpr = $this->diffMinutesExpression('opened_at', 'assigned_at');

        return $query->selectRaw("
            SUM(CASE WHEN status_id = ? THEN 1 ELSE 0 END) as open_tickets,
            SUM(CASE WHEN status_id = ? THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN budget_status = ? THEN 1 ELSE 0 END) as budget_pending_tickets,
            SUM(CASE WHEN status_id = ? AND opened_at IS NOT NULL AND closed_at IS NOT NULL THEN 1 ELSE 0 END) as closed_tickets,
            AVG(CASE WHEN status_id = ? AND opened_at IS NOT NULL AND closed_at IS NOT NULL
                THEN {$diffExpr} END) as avg_resolution,
            AVG(CASE WHEN opened_at IS NOT NULL AND assigned_at IS NOT NULL
                THEN {$diffWaitExpr} END) as avg_waiting,
            SUM(CASE WHEN status_id = ? AND opened_at IS NOT NULL AND closed_at IS NOT NULL
                AND {$diffExpr} <= ? THEN 1 ELSE 0 END) as sla_met
        ", [
            $this->openStatusId,
            $this->inProgressStatusId,
            BudgetStatusEnum::Pending->value,
            $this->closedStatusId,
            $this->closedStatusId,
            $this->closedStatusId,
            $this->slaTargetMinutes,
        ]);
    }

    private function diffMinutesExpression(string $start, string $end): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "(julianday({$end}) - julianday({$start})) * 1440",
            'pgsql' => "EXTRACT(EPOCH FROM {$end} - {$start}) / 60",
            default => "TIMESTAMPDIFF(MINUTE, {$start}, {$end})",
        };
    }
}
