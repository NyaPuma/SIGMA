<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Ticket\Queries\MonthlyTicketsQuery;
use App\Domain\Ticket\Queries\TicketKpiQuery;
use App\Domain\Ticket\Queries\TicketPriorityQuery;
use App\Domain\Ticket\Queries\TopEntitiesQuery;
use App\Enums\BudgetStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\StockMovementTypeEnum;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Audit;
use App\Models\Notification;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Ticket;
use App\Models\UserProfile;
use App\Support\DateSql;
use App\ValueObjects\SqlFragment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class AnalyticsDashboardService
{
    public function __construct(
        private readonly TicketStatusService $statusService,
    ) {}

    /**
     * Returns the complete analytics dashboard payload (with cache).
     *
     * @return array<string, mixed>
     */
    public function getDashboardPayload(): array
    {
        return Cache::remember(
            'analytics_dashboard_payload:'.app()->getLocale(),
            60,
            fn () => $this->buildPayload(),
        );
    }

    /**
     * Builds the data payload for the analytics dashboard.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(): array
    {
        $openStatusId = $this->statusService->getByName(TicketStatusEnum::Open);
        $inProgressStatusId = $this->statusService->getByName(TicketStatusEnum::InProgress);
        $closedStatusId = $this->statusService->getByName(TicketStatusEnum::Closed);

        $baseQuery = Ticket::query()->whereNull('tickets.deleted_at');

        $slaTarget = config('services.analytics.sla_target_minutes', 480);
        $slaTargetMinutes = is_numeric($slaTarget) ? (int) $slaTarget : 480;

        $kpiData = (new TicketKpiQuery($openStatusId, $inProgressStatusId, $closedStatusId, $slaTargetMinutes))->execute();
        $priorityData = (new TicketPriorityQuery($baseQuery))->execute();
        $monthlyData = (new MonthlyTicketsQuery($openStatusId, $inProgressStatusId, $closedStatusId, now()))->execute();
        $topEntities = new TopEntitiesQuery($baseQuery);

        // A month (or a fresh deployment) with nothing closed has no SLA evidence at all.
        // Reporting 100 would show a perfect score precisely when nobody can be measured,
        // so the figure is null and the dashboard renders it as "sem dados".
        $slaSuccess = $kpiData['closed_tickets'] > 0
            ? round(($kpiData['sla_met'] / $kpiData['closed_tickets']) * 100, 1)
            : null;

        $monthlyPerformance = $this->monthlyPerformanceData($monthlyData['labels']);

        return [
            'average_resolution_minutes' => round($kpiData['avg_resolution'], 1),
            'average_waiting_minutes' => round($kpiData['avg_waiting'], 1),
            'open_tickets' => $kpiData['open_tickets'],
            'in_progress_tickets' => $kpiData['in_progress_tickets'],
            'waiting_budget_tickets' => $kpiData['budget_pending_tickets'],
            'closed_tickets' => $kpiData['closed_tickets'],
            // Must be the key SystemSettingsService manages: applyOverrides() writes the saved
            // value to services.custom.analytics.*, so reading services.analytics.* would make
            // the "Disponibilidade do sistema" field in Settings have no effect here.
            'system_availability' => config('services.custom.analytics.system_availability', 99.9),
            'sla_success' => $slaSuccess,
            'by_priority' => [
                'labels' => collect([
                    TicketPriorityEnum::Low->label(),
                    TicketPriorityEnum::Medium->label(),
                    TicketPriorityEnum::High->label(),
                ]),
                'data' => collect([$priorityData['low'], $priorityData['medium'], $priorityData['high']]),
            ],
            'ticket_status_breakdown' => [
                'labels' => collect([
                    TicketStatusEnum::Open->label(),
                    TicketStatusEnum::InProgress->label(),
                    TicketStatusEnum::PendingBudget->label(),
                    TicketStatusEnum::Closed->label(),
                ]),
                'data' => collect([
                    $kpiData['open_tickets'],
                    $kpiData['in_progress_tickets'],
                    $kpiData['budget_pending_tickets'],
                    $kpiData['closed_tickets'],
                ]),
            ],
            'monthly_tickets' => [
                'labels' => $monthlyData['labels'],
                'open' => $monthlyData['open'],
                'in_progress' => $monthlyData['in_progress'],
                'closed' => $monthlyData['closed'],
            ],
            'monthly_cost' => [
                'labels' => $monthlyData['labels'],
                'data' => $monthlyData['cost_data'],
            ],
            'monthly_sla' => [
                'labels' => $monthlyData['labels'],
                'data' => $monthlyPerformance['sla'],
            ],
            'monthly_mttr' => [
                'labels' => $monthlyData['labels'],
                'data' => $monthlyPerformance['mttr'],
            ],
            'by_urgency' => $this->urgencyBreakdown($baseQuery),
            'by_room' => $this->roomBreakdown($baseQuery),
            'by_budget_status' => $this->budgetBreakdown($baseQuery),
            'by_source' => $this->sourceBreakdown($baseQuery),
            'cost_by_equipment' => $this->costByEquipment(),
            'stock_monthly' => $this->stockMonthlyData($monthlyData['labels']),
            'low_stock_parts' => $this->lowStockParts(),
            'notifications_by_type' => $this->notificationsByType(),
            'users_by_role' => $this->usersByRole(),
            'top_equipments' => $topEntities->getTopEquipments(),
            'top_rooms' => $topEntities->getTopRooms(),
            'top_technicians' => $topEntities->getTopTechnicians(),
            'recent_activity' => $this->getRecentActivity(),
        ];
    }

    /**
     * Monthly performance: SLA rate (percentage) and MTTR (minutes) by month.
     *
     * @param  array<int, string>  $monthLabels
     * @return array{sla: array<int, float|null>, mttr: array<int, float|null>}
     */
    private function monthlyPerformanceData(array $monthLabels): array
    {
        if ($monthLabels === []) {
            return ['sla' => [], 'mttr' => []];
        }

        $start = Carbon::parse($monthLabels[0])->startOfMonth();
        $end = Carbon::parse($monthLabels[array_key_last($monthLabels)])->endOfMonth();

        $monthExpr = DateSql::month('opened_at');
        $diffExpr = DateSql::minutesBetween('opened_at', 'closed_at');
        $targetRaw = config('services.analytics.sla_target_minutes', 480);
        $targetMinutes = is_numeric($targetRaw) ? (int) $targetRaw : 480;
        $closedId = $this->statusService->getByName(TicketStatusEnum::Closed);

        $rows = Ticket::query()
            ->select(new SqlFragment("
                {$monthExpr} as month,
                AVG(CASE WHEN status_id = ? AND opened_at IS NOT NULL AND closed_at IS NOT NULL
                    THEN {$diffExpr} END) as avg_resolution,
                SUM(CASE WHEN status_id = ? AND opened_at IS NOT NULL AND closed_at IS NOT NULL
                    AND {$diffExpr} <= ? THEN 1 ELSE 0 END) as sla_met,
                SUM(CASE WHEN status_id = ? AND opened_at IS NOT NULL AND closed_at IS NOT NULL
                    THEN 1 ELSE 0 END) as closed_count
            "))
            ->addBinding([$closedId, $closedId, $targetMinutes, $closedId], 'select')
            ->whereNull('tickets.deleted_at')
            ->whereNotNull('opened_at')
            ->whereBetween('opened_at', [$start, $end])
            ->groupBy(new SqlFragment($monthExpr))
            ->get()
            ->keyBy('month');

        $sla = [];
        $mttr = [];

        foreach ($monthLabels as $month) {
            $row = $rows->get($month);
            $closedCount = data_get($row, 'closed_count');
            $slaMetCount = data_get($row, 'sla_met');
            $closed = is_numeric($closedCount) ? (int) $closedCount : 0;
            $slaMet = is_numeric($slaMetCount) ? (int) $slaMetCount : 0;
            $sla[] = $closed > 0 ? round(($slaMet / $closed) * 100, 1) : null;
            $averageResolution = data_get($row, 'avg_resolution');
            $mttr[] = is_numeric($averageResolution) ? round((float) $averageResolution, 1) : null;
        }

        return [
            'sla' => $sla,
            'mttr' => $mttr,
        ];
    }

    /**
     * Ticket distribution by urgency.
     *
     * @param  Builder<Ticket>  $baseQuery
     * @return array{labels: Collection<int, string>, data: Collection<int, int<0, max>>}
     */
    private function urgencyBreakdown(Builder $baseQuery): array
    {
        $urgent = (clone $baseQuery)->where('urgent', true)->count();
        $normal = (clone $baseQuery)->where(fn ($q) => $q->where('urgent', false)->orWhereNull('urgent'))->count();

        return [
            'labels' => collect([
                __('analytics_data.urgent'),
                __('analytics_data.normal'),
            ])->map(static fn (mixed $label): string => (string) $label),
            'data' => collect([$urgent, $normal])->map(static fn (mixed $value): int => (int) $value),
        ];
    }

    /**
     * Ticket distribution by room (top 8).
     *
     * @param  Builder<Ticket>  $baseQuery
     * @return array{labels: Collection<int, string>, data: Collection<int, int>}
     */
    private function roomBreakdown(Builder $baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->leftJoin('rooms', 'tickets.room_id', '=', 'rooms.id')
            ->select('rooms.name', DB::raw('COUNT(*) as total'))
            ->whereNotNull('tickets.room_id')
            ->groupBy('rooms.id', 'rooms.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'labels' => $rows->pluck('name')
                ->map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '')
                ->values(),
            'data' => $rows->pluck('total')
                ->map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0)
                ->values(),
        ];
    }

    /**
     * Ticket distribution by budget status.
     *
     * @param  Builder<Ticket>  $baseQuery
     * @return array{labels: Collection<int, string>, data: Collection<int, int>}
     */
    private function budgetBreakdown(Builder $baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->selectRaw('budget_status, COUNT(*) as total')
            ->whereNotNull('budget_status')
            ->groupBy('budget_status')
            ->get()
            ->pluck('total', 'budget_status');

        $labels = collect([BudgetStatusEnum::Pending, BudgetStatusEnum::Approved, BudgetStatusEnum::Rejected]);

        return [
            'labels' => $labels->map(fn ($s) => $s->label())->values(),
            'data' => $labels->map(function ($status) use ($rows): int {
                $value = $rows[$status->value] ?? 0;

                return is_numeric($value) ? (int) $value : 0;
            })->values(),
        ];
    }

    /**
     * Ticket distribution by source (web, qr, api, ...).
     *
     * @param  Builder<Ticket>  $baseQuery
     * @return array{labels: Collection<int, string>, data: Collection<int, int>}
     */
    private function sourceBreakdown(Builder $baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'web') as source, COUNT(*) as total")
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        $labels = collect(['web', 'qr', 'api', 'mobile', 'telefone']);

        $sourceLabels = [
            'web' => __('analytics_data.web'),
            'qr' => __('analytics_data.qr'),
            'api' => __('analytics_data.api'),
            'mobile' => __('analytics_data.mobile'),
            'telefone' => __('analytics_data.phone'),
        ];

        return [
            'labels' => $labels->map(fn (string $s): string => (string) ($sourceLabels[$s] ?? $s))->values(),
            'data' => $labels->map(function (string $s) use ($rows): int {
                $total = data_get($rows->firstWhere('source', $s), 'total');

                return is_numeric($total) ? (int) $total : 0;
            })->values(),
        ];
    }

    /**
     * Total intervention cost by equipment (top 8).
     *
     * @return array{labels: Collection<int, string>, data: Collection<int, float>}
     */
    private function costByEquipment(): array
    {
        $rows = Ticket::query()
            ->leftJoin('equipments', 'tickets.equipment_id', '=', 'equipments.id')
            ->select('equipments.name', DB::raw('COALESCE(SUM(tickets.actual_cost), 0) as total'))
            ->whereNull('tickets.deleted_at')
            ->whereNotNull('tickets.equipment_id')
            ->whereNotNull('tickets.actual_cost')
            ->groupBy('equipments.id', 'equipments.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'labels' => $rows->pluck('name')
                ->map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '')
                ->values(),
            'data' => $rows->pluck('total')
                ->map(static fn (mixed $value): float => is_numeric($value) ? round((float) $value, 2) : 0.0)
                ->values(),
        ];
    }

    /**
     * Monthly stock movement (entries and exits).
     *
     * @param  array<int, string>  $monthLabels
     * @return array{labels: array<int, string>, in: array<int, int>, out: array<int, int>}
     */
    private function stockMonthlyData(array $monthLabels): array
    {
        if ($monthLabels === []) {
            return ['labels' => [], 'in' => [], 'out' => []];
        }

        $start = Carbon::parse($monthLabels[0])->startOfMonth();
        $end = Carbon::parse($monthLabels[array_key_last($monthLabels)])->endOfMonth();

        $monthExpr = DateSql::month('created_at');

        $rows = StockMovement::query()
            ->select(new SqlFragment("
                {$monthExpr} as month,
                SUM(CASE WHEN movement_type IN (?, ?) THEN ABS(quantity) ELSE 0 END) as total_in,
                SUM(CASE WHEN movement_type = ? THEN ABS(quantity) ELSE 0 END) as total_out
            "))
            ->addBinding([StockMovementTypeEnum::In->value, StockMovementTypeEnum::Return->value, StockMovementTypeEnum::Out->value], 'select')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(new SqlFragment($monthExpr))
            ->get()
            ->keyBy('month');

        $in = [];
        $out = [];

        foreach ($monthLabels as $month) {
            $row = $rows->get($month);
            $totalIn = data_get($row, 'total_in');
            $totalOut = data_get($row, 'total_out');
            $in[] = is_numeric($totalIn) ? (int) $totalIn : 0;
            $out[] = is_numeric($totalOut) ? (int) $totalOut : 0;
        }

        return [
            'labels' => $monthLabels,
            'in' => $in,
            'out' => $out,
        ];
    }

    /**
     * Low stock alert parts (top 8 by criticality).
     *
     * @return array{labels: Collection<int, string>, data: Collection<int, int>}
     */
    private function lowStockParts(): array
    {
        $parts = Part::query()
            ->lowStock()
            ->orderByRaw('CASE WHEN min_stock > 0 THEN current_stock * 1.0 / min_stock ELSE 0 END')
            ->orderBy('current_stock')
            ->limit(8)
            ->get(['name', 'current_stock']);

        return [
            'labels' => $parts->pluck('name')
                ->map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '')
                ->values(),
            'data' => $parts->pluck('current_stock')
                ->map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0)
                ->values(),
        ];
    }

    /**
     * Notifications by type (top 8).
     *
     * @return array{labels: Collection<int, string>, data: Collection<int, int>}
     */
    private function notificationsByType(): array
    {
        $rows = Notification::query()
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'labels' => $rows->pluck('type')->map(function (mixed $type) {
                $value = is_scalar($type) ? (string) $type : '';

                return match ($value) {
                    'ticket_updated' => __('analytics_data.ticket_updated'),
                    'ticket_assigned' => __('analytics_data.ticket_assigned'),
                    'comment_added' => __('analytics_data.comment_added'),
                    'attachment_added' => __('analytics_data.attachment_added'),
                    'budget_request' => __('analytics_data.budget_request'),
                    default => NotificationTypeEnum::tryFrom($value)?->label() ?? ucfirst($value),
                };
            })->values(),
            'data' => $rows->pluck('total')
                ->map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0)
                ->values(),
        ];
    }

    /**
     * User distribution by role.
     *
     * @return array{labels: Collection<int, string>, data: Collection<int, int>}
     */
    private function usersByRole(): array
    {
        $rows = UserProfile::query()
            ->leftJoin('users', 'users.profile_id', '=', 'user_profiles.id')
            ->whereNull('users.deleted_at')
            ->select('user_profiles.name', DB::raw('COUNT(users.id) as total'))
            ->groupBy('user_profiles.id', 'user_profiles.name')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('name')
                ->map(static fn (mixed $name): string => ucfirst(is_scalar($name) ? (string) $name : ''))
                ->values(),
            'data' => $rows->pluck('total')
                ->map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0)
                ->values(),
        ];
    }

    /**
     * Gets recent activity recorded in system audits.
     *
     * @return Collection<int, array{title: string, description: string, time: string}>
     */
    private function getRecentActivity(): Collection
    {
        return Audit::query()
            ->with('user')
            ->latest()
            ->take(6)
            ->get()
            ->map(function (Audit $audit): array {
                $userName = $audit->user?->name;
                $createdAt = $audit->created_at?->diffForHumans();

                return [
                    'title' => is_scalar($userName) ? (string) $userName : 'Sistema',
                    'description' => $this->getAuditDescription($audit->event),
                    'time' => is_string($createdAt) ? $createdAt : 'recentemente',
                ];
            })
            ->values();
    }

    /**
     * Translates the audit event into a readable description.
     */
    private function getAuditDescription(string $event): string
    {
        return match ($event) {
            'created' => 'Created a new entry in the system.',
            'updated' => 'Updated fields in a record.',
            'deleted' => 'Removed a record from the system.',
            default => 'Action logged in audit.',
        };
    }
}
