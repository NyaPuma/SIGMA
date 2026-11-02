<?php

namespace App\Domain\Ticket\Queries;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class MonthlyTicketsQuery
{
    public function __construct(
        private int $openStatusId,
        private int $inProgressStatusId,
        private int $closedStatusId,
        private Carbon $now,
    ) {}

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $monthKeys = $this->generateMonthKeys();
        $rows = $this->buildQuery($monthKeys)->keyBy('month');

        return $this->formatResults($monthKeys, $rows);
    }

    /** @return array<int, string> */
    private function generateMonthKeys(): array
    {
        $keys = [];
        foreach (range(5, 0) as $offset) {
            // Normalize to start of month to prevent day overflow (e.g. 31 -> next month)
            $keys[] = $this->now->copy()->startOfMonth()->subMonths($offset)->format('Y-m');
        }

        return $keys;
    }

    /**
     * @param  array<int, string>  $monthKeys
     * @return Collection<int, Ticket>
     */
    private function buildQuery(array $monthKeys): Collection
    {
        $startMonth = $this->now->copy()->subMonths(5)->startOfMonth()->toDateTimeString();
        $endMonth = $this->now->copy()->endOfMonth()->toDateTimeString();

        $monthExpr = $this->monthExpression('opened_at');

        return Ticket::query()
            ->selectRaw("
                {$monthExpr} as month,
                SUM(CASE WHEN status_id = ? THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status_id = ? THEN 1 ELSE 0 END) as in_progress_count,
                SUM(CASE WHEN status_id = ? THEN 1 ELSE 0 END) as closed_count,
                SUM(CASE WHEN status_id = ? AND closed_at IS NOT NULL AND actual_cost IS NOT NULL THEN actual_cost ELSE 0 END) as total_cost
            ", [$this->openStatusId, $this->inProgressStatusId, $this->closedStatusId, $this->closedStatusId])
            ->whereNull('tickets.deleted_at')
            ->whereNotNull('opened_at')
            ->whereBetween('opened_at', [$startMonth, $endMonth])
            ->groupByRaw($monthExpr)
            ->get();
    }

    private function monthExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * @param  array<int, string>  $monthKeys
     * @param  \Illuminate\Support\Collection<string, Ticket>  $rows
     * @return array<string, mixed>
     */
    private function formatResults(array $monthKeys, $rows): array
    {
        $open = $inProgress = $closed = $costData = [];

        foreach ($monthKeys as $key) {
            $row = $rows->get($key);
            $openCount = $row !== null ? ($row->open_count ?? null) : null;
            $inProgressCount = $row !== null ? ($row->in_progress_count ?? null) : null;
            $closedCount = $row !== null ? ($row->closed_count ?? null) : null;
            $totalCost = $row !== null ? ($row->total_cost ?? null) : null;
            $open[] = is_numeric($openCount) ? (int) $openCount : 0;
            $inProgress[] = is_numeric($inProgressCount) ? (int) $inProgressCount : 0;
            $closed[] = is_numeric($closedCount) ? (int) $closedCount : 0;
            $costData[] = round(is_numeric($totalCost) ? (float) $totalCost : 0.0, 2);
        }

        return [
            'labels' => $monthKeys,
            'open' => $open,
            'in_progress' => $inProgress,
            'closed' => $closed,
            'cost_data' => $costData,
        ];
    }
}
