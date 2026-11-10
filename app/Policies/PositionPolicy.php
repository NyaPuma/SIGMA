<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

final class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.positions.view');
    }

    public function view(User $user, Position $position): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.positions.manage');
    }

    public function update(User $user, Position $position): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Position $position): bool
    {
        return $this->create($user);
    }
}
