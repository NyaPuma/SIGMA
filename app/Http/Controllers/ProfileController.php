<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class ProfileController extends Controller
{
    /**
     * Changes the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            return response()->json([
                'message' => __('auth.Password atual incorreta'),
            ], 403);
        }

        $user->password = Hash::make($request->string('new_password')->toString());
        $user->save();

        return response()->json([
            'message' => __('messages.Password alterada com sucesso.'),
        ]);
    }

    /**
     * Updates the profile data of the authenticated user.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
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

        return response()->json([
            'message' => __('messages.Perfil atualizado com sucesso.'),
            'user' => new UserResource($user),
        ]);
    }
}
