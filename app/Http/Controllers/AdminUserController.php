<?php

namespace App\Http\Controllers;

use App\Actions\CreateUserAction;
use App\Actions\UpdateUserAction;
use App\DTOs\StoreUserData;
use App\DTOs\UpdateUserData;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserProfileResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserProfile;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminUserController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly CreateUserAction $createUserAction,
        private readonly UpdateUserAction $updateUserAction,
    ) {}

    /**
     * Lists all users with their respective profiles.
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('viewAny', User::class);

        // 2. Get filters from request
        $search = $request->query('q');
        $role = $request->query('role');
        $status = $request->query('status');

        $search = is_string($search) ? $search : null;
        $role = is_string($role) ? $role : null;
        $status = is_string($status) ? $status : null;

        // 3. Search for users with eager loading of profile and applied filters
        $users = $this->userRepository->getAll(['profile'], $search, $role, $status);

        // 4. Standardized paginated response (preserves data/links/meta structure)
        return response()->json([
            'users' => UserResource::collection($users)->response()->getData(true),
        ]);
    }

    /**
     * Registers a new user in the system.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('create', User::class);

        // 2. Execute DTO and Action for creation
        $data = StoreUserData::fromRequest($request->validated());
        $newUser = $this->createUserAction->execute($data);

        $newUser->loadMissing('profile');

        return response()->json([
            'message' => __('messages.Utilizador criado com sucesso.'),
            'user' => new UserResource($newUser),
        ], 201);
    }

    /**
     * Updates an existing user's information.
     */
    public function update(UpdateUserRequest $request, User $targetUser): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('update', $targetUser);

        // 2. Execute DTO and Action for update
        $data = UpdateUserData::fromRequest($request->validated());
        $updatedUser = $this->updateUserAction->execute($targetUser, $data);

        $updatedUser->loadMissing('profile');

        return response()->json([
            'message' => __('messages.Utilizador atualizado com sucesso.'),
            'user' => new UserResource($updatedUser),
        ]);
    }

    /**
     * Deactivates a user account in the system.
     */
    public function inactivate(Request $request, User $targetUser): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('inactivate', $targetUser);

        // 2. Business rule: prevents the administrator from deactivating themselves
        if ($this->authenticatedUser($request)->id === $targetUser->id) {
            return response()->json([
                'message' => __('common.Não é possível inativar a sua própria conta.'),
            ], 422);
        }

        // 3. Deactivate user via Repository
        $this->userRepository->inactivate($targetUser);

        $targetUser->loadMissing('profile');

        return response()->json([
            'message' => __('messages.Utilizador inativado com sucesso.'),
            'user' => new UserResource($targetUser),
        ]);
    }

    /**
     * Soft-deletes a user account from the system.
     */
    public function destroy(Request $request, User $targetUser): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('delete', $targetUser);

        // 2. Business rule: prevents the administrator from deleting themselves
        if ($this->authenticatedUser($request)->id === $targetUser->id) {
            return response()->json([
                'message' => __('common.Não é possível apagar a sua própria conta.'),
            ], 422);
        }

        // 3. Soft delete of user via Repository
        $this->userRepository->delete($targetUser);

        return response()->json([
            'message' => __('messages.Utilizador apagado com sucesso.'),
        ]);
    }

    /**
     * Lists all available profiles/roles in the system.
     */
    public function profiles(Request $request): JsonResponse
    {
        // 1. Authorization via Policy (or reuse of UserProfile model permission)
        $this->authorize('viewAny', UserProfile::class);

        $profiles = UserProfile::withCount('users')->get();

        return response()->json([
            'profiles' => UserProfileResource::collection($profiles),
        ]);
    }
}
