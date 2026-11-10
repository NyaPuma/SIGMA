<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSchedule;

final class WorkSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.schedules.view');
    }

    public function view(User $user, WorkSchedule $schedule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.schedules.manage');
    }

    public function update(User $user, WorkSchedule $schedule): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, WorkSchedule $schedule): bool
    {
        return $this->create($user);
    }
}
