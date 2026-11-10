<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeDocument;
use App\Models\User;

final class EmployeeDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.employees.view');
    }

    public function view(User $user, EmployeeDocument $document): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.employees.manage');
    }

    public function update(User $user, EmployeeDocument $document): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, EmployeeDocument $document): bool
    {
        return $this->create($user);
    }
}
