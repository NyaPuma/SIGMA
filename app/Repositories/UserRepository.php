<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\SqlPattern;
use Illuminate\Pagination\LengthAwarePaginator;

final class UserRepository implements UserRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', strtolower($email))->first();
    }

    /**
     * {@inheritDoc}
     */
    public function getAll(array $relations = [], ?string $search = null, ?string $role = null, ?string $status = null): LengthAwarePaginator
    {
        $query = User::with($relations)->latest();

        // Search filter
        if ($search) {
            SqlPattern::whereContains($query, ['name', 'email'], $search);
        }

        // Role filter
        if ($role) {
            $query->whereHas('profile', fn ($q) => $q->where('name', $role));
        }

        // Status filter
        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        return $query->paginate(15);
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveTechnicians(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = User::whereHas('profile', fn ($q) => $q->where('name', UserRoleEnum::Technician->value))
            ->where('active', true)
            ->get(['id', 'name'])
            ->toArray();

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function getAdmins(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = User::whereHas('profile', fn ($q) => $q->where('name', UserRoleEnum::Admin->value))
            ->get(['id', 'name'])
            ->toArray();

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }

    /**
     * {@inheritDoc}
     */
    public function inactivate(User $user): bool
    {
        return $user->update(['active' => false]);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }
}
