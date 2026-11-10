<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

final class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.attendance.view');
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.attendance.manage');
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return $this->create($user);
    }
}
