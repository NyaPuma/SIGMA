<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class ProfileController extends Controller
{
    /**
     * Changes the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request, UserService $userService): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            return response()->json([
                'message' => __('auth.Password atual incorreta'),
            ], 403);
        }

        $user->password = Hash::make($request->string('new_password')->toString());
        $user->save();

        $this->revokeTokens($userService, $request, $user);

        return response()->json([
            'message' => __('messages.Password alterada com sucesso.'),
            're_authentication_required' => true,
        ]);
    }

    /**
     * Updates the profile data of the authenticated user.
     */
    public function updateProfile(UpdateProfileRequest $request, UserService $userService): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $newPassword = $request->string('password')->toString();

        if ($newPassword !== '') {
            $user->password = Hash::make($newPassword);
        }

        $name = $request->string('name')->toString();

        if ($name !== '') {
            $user->name = $name;
        }

        $user->save();
        $user->loadMissing('profile');

        $reauthenticated = $newPassword !== '';

        if ($reauthenticated) {
            $this->revokeTokens($userService, $request, $user);
        }

        return response()->json([
            'message' => __('messages.Perfil atualizado com sucesso.'),
            're_authentication_required' => $reauthenticated,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Issues a fresh API token after a password change.
     *
     * Changing the password is the remediation an owner reaches for once a token leaks;
     * leaving the stored token untouched meant the leaked one kept working forever. The
     * session copy is not refreshed (`withSession: false`), so every previously issued
     * token, including this device's, has to authenticate again.
     */
    private function revokeTokens(UserService $userService, Request $request, User $user): void
    {
        $userService->createToken($user, $request, withSession: false);
    }
}
