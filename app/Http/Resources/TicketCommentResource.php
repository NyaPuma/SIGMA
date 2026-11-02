<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketComment */
final class TicketCommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'user_id' => $this->user_id,
            'parent_id' => $this->parent_id,
            'comment' => $this->comment,
            'is_internal' => $this->is_internal,
            'edited_at' => $this->edited_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'replies' => $this->whenLoaded('replies', fn () => self::collection($this->replies)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
