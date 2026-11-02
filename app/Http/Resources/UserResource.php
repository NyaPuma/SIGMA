<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'active' => $this->active,
            'profile_id' => $this->profile_id,
            'profile' => $this->whenLoaded('profile', fn () => $this->profile ? [
                'id' => $this->profile->id,
                'name' => $this->profile->name,
                'description' => $this->profile->description,
            ] : null),
            'avatar_path' => $this->avatar_path,
            'avatar_disk' => $this->avatar_disk,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'login_attempts' => $this->login_attempts,
            'locked_until' => $this->locked_until?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
