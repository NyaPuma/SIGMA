<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Equipment;
use App\Models\Part;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class ActivityFeedController extends Controller
{
    /**
     * Recent activity feed in the format expected by the component
     * x-ui.analytics.activity-timeline-card.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Audit::class);

        $audits = Audit::query()
            ->with('user')
            ->latest()
            ->take(15)
            ->get();

        $activities = $audits->map(fn (Audit $audit): array => [
            'id' => $audit->id,
            'title' => $this->titleFor($audit),
            'description' => $this->descriptionFor($audit),
            'time_ago' => $audit->created_at?->diffForHumans() ?? __('activity.time_ago'),
            'icon_bg' => $this->iconBgFor($audit),
            'dot_color' => $this->dotColorFor($audit),
        ]);

        return response()->json($activities->values());
    }

    private function titleFor(Audit $audit): string
    {
        return match ($audit->auditable_type) {
            Ticket::class => match ($audit->event) {
                'created' => __('activity.title_ticket_created'),
                'updated' => __('activity.title_ticket_updated'),
                'deleted' => __('activity.title_ticket_deleted'),
                default => __('activity.title_ticket_changed'),
            },
            Part::class => __('activity.title_part'),
            Equipment::class => __('activity.title_equipment'),
            Room::class => __('activity.title_room'),
            User::class => __('activity.title_user'),
            default => __('activity.title_default'),
        };
    }

    private function descriptionFor(Audit $audit): string
    {
        $systemLabel = __('activity.system');
        $userName = $audit->user?->name;
        $user = is_string($userName) ? $userName : (is_string($systemLabel) ? $systemLabel : 'Sistema');
        $subject = $this->subjectName($audit);

        $subjectPart = $subject !== null ? sprintf('«%s»', $subject) : null;

        $args = [
            'subject' => $subjectPart ?? '',
            'user' => $user,
        ];

        return match ($audit->auditable_type) {
            Ticket::class => match ($audit->event) {
                'created' => __('activity.desc_ticket_created', ['subject' => $args['subject'], 'user' => $user]),
                'updated' => __('activity.desc_ticket_updated', ['subject' => $args['subject'], 'user' => $user]),
                'deleted' => __('activity.desc_ticket_deleted', ['subject' => $args['subject'], 'user' => $user]),
                default => __('activity.desc_ticket_changed', ['subject' => $args['subject'], 'user' => $user]),
            },
            Part::class => __('activity.desc_part', $args),
            Equipment::class => __('activity.desc_equipment', $args),
            Room::class => __('activity.desc_room', $args),
            User::class => __('activity.desc_user', ['user' => $user]),
            default => __('activity.desc_default', ['user' => $user]),
        };
    }

    private function subjectName(Audit $audit): ?string
    {
        $values = $audit->new_values;

        if (! is_array($values)) {
            return null;
        }

        if (isset($values['title']) && is_scalar($values['title'])) {
            return (string) $values['title'];
        }

        return isset($values['name']) && is_scalar($values['name']) ? (string) $values['name'] : null;
    }

    private function iconBgFor(Audit $audit): string
    {
        return match ($audit->auditable_type) {
            Ticket::class => 'bg-blue-500/10',
            Part::class, Equipment::class => 'bg-emerald-500/10',
            Room::class => 'bg-amber-500/10',
            User::class => 'bg-purple-500/10',
            default => 'bg-slate-500/10',
        };
    }

    private function dotColorFor(Audit $audit): string
    {
        return match ($audit->auditable_type) {
            Ticket::class => 'bg-blue-500',
            Part::class, Equipment::class => 'bg-emerald-500',
            Room::class => 'bg-amber-500',
            User::class => 'bg-purple-500',
            default => 'bg-slate-500',
        };
    }
}
