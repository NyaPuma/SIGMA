<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

final class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.departments.manage');
    }

    public function update(User $user, Department $department): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->create($user);
    }
}
