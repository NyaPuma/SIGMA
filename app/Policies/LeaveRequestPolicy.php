<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

final class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.leaves.view');
    }

    public function view(User $user, LeaveRequest $leave): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.leaves.manage');
    }

    public function update(User $user, LeaveRequest $leave): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.leaves.manage');
    }

    public function delete(User $user, LeaveRequest $leave): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.leaves.manage');
    }

    public function approve(User $user, LeaveRequest $leave): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.leaves.approve');
    }
}
