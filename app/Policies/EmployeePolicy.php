<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

final class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.employees.view');
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.employees.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.employees.manage');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.employees.manage');
    }
}
