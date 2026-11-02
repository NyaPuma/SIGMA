<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    /**
     * Find a user by their ID.
     */
    public function findById(int $id): ?User;

    /**
     * Find a user by their email address.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Return a paginated list of users.
     *
     * @param  array<int, string>  $relations
     * @return LengthAwarePaginator<int, User>
     */
    public function getAll(array $relations = [], ?string $search = null, ?string $role = null, ?string $status = null): LengthAwarePaginator;

    /**
     * Return all active technicians.
     *
     * @return array<int, User>
     */
    public function getActiveTechnicians(): array;

    /**
     * Return all administrators.
     *
     * @return array<int, User>
     */
    public function getAdmins(): array;

    /**
     * Create a new user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;

    /**
     * Update an existing user.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): bool;

    /**
     * Inactivate a user.
     */
    public function inactivate(User $user): bool;

    /**
     * Delete (soft delete) a user.
     */
    public function delete(User $user): bool;
}
