<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
final class TicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'priority_label' => TicketPriorityEnum::normalize((string) $this->priority)?->label(),
            'urgent' => $this->urgent,
            'user_id' => $this->user_id,
            'reporter_name' => $this->reporter_name,
            'reporter_contact' => $this->reporter_contact,
            'source' => $this->source,
            'assigned_to' => $this->assigned_to,
            'equipment_id' => $this->equipment_id,
            'room_id' => $this->room_id,
            'status_id' => $this->status_id,
            'status' => $this->status,
            'status_label' => TicketStatusEnum::normalize($this->status?->name)?->label(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'technician' => $this->whenLoaded('technician', fn () => $this->technician ? [
                'id' => $this->technician->id,
                'name' => $this->technician->name,
            ] : null),
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->id,
                'name' => $this->equipment->name,
            ] : null),
            'room' => $this->whenLoaded('room', fn () => $this->room ? [
                'id' => $this->room->id,
                'name' => $this->room->name,
                'code' => $this->room->code,
            ] : null),
            'status_name' => $this->status?->name,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'in_progress_at' => $this->in_progress_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'scheduled_end' => $this->scheduled_end?->toIso8601String(),
            'scheduled' => $this->scheduled,
            'due_at' => $this->due_at?->toIso8601String(),
            'budget_requested' => $this->budget_requested,
            'budget_status' => $this->budget_status,
            'budget_amount' => $this->budget_amount,
            'budget_requested_at' => $this->budget_requested_at?->toIso8601String(),
            'resolution' => $this->resolution,
            'minutes_spent' => $this->minutes_spent,
            'sla_breached' => $this->sla_breached,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
