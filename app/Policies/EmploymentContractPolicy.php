<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmploymentContract;
use App\Models\User;

final class EmploymentContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.contracts.view');
    }

    public function view(User $user, EmploymentContract $contract): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('hr.contracts.manage');
    }

    public function update(User $user, EmploymentContract $contract): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, EmploymentContract $contract): bool
    {
        return $this->create($user);
    }
}
