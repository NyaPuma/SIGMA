<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class UserService
{
    /**
     * Returns available user roles.
     *
     * @return array<int, string>
     */
    public function getAvailableRoles(): array
    {
        return UserRoleEnum::values();
    }

    /**
     * Ensures the user has a default profile associated.
     */
    public function ensureDefaultProfile(User $user): void
    {
        if ($user->profile_id) {
            return;
        }

        /** @var UserProfile $defaultProfile */
        $defaultProfile = UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
        $user->profile_id = $defaultProfile->id;
    }

    /**
     * Generates a secure hash for the API token using the application key.
     */
    public function hashToken(string $token): string
    {
        return User::hashToken($token);
    }

    /**
     * Creates and persists a new API token for the user and updates the session.
     *
     * @param  bool  $withSession  When false, the token is not linked to the current request session
     *                             (needed when registration is done by another user,
     *                             preventing the registrant's session/cookies from being assumed
     *                             by the new user).
     */
    public function createToken(User $user, Request $request, bool $withSession = true): string
    {
        /** @var int $tokenLength */
        $tokenLength = config('services.custom.tokens.length', 60);

        $plainToken = Str::random($tokenLength);
        $user->api_token = $this->hashToken($plainToken);
        $user->token_created_at = now();
        $user->save();

        if ($withSession && $request->hasSession()) {
            $request->session()->regenerate();
            $request->session()->put('api_token', $plainToken);
            $request->session()->save();
        }
        $user->load('profile');

        return $plainToken;
    }

    /**
     * Builds the authentication JSON response with the respective token cookies.
     */
    public function buildAuthResponse(User $user, string $plainToken, Request $request, int $statusCode = 200): JsonResponse
    {
        $secure = $request->secure();

        return response()->json(['user' => $user, 'token' => $plainToken], $statusCode)
            ->cookie('api_token', $plainToken, 60 * 24 * 30, '/', null, $secure, true, false, 'Lax')
            ->cookie('auth_token', $plainToken, 60 * 24 * 30, '/', null, $secure, true, false, 'Lax');
    }

    /**
     * Builds the session termination (logout) JSON response, invalidating session and removing cookies.
     */
    public function buildLogoutResponse(Request $request): JsonResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $secure = $request->secure();

        return response()->json(['message' => __('messages.Session terminated successfully.')])
            ->withCookie(cookie('api_token', null, -1, '/', null, $secure, true, false, 'Lax'))
            ->withCookie(cookie('auth_token', null, -1, '/', null, $secure, false, false, 'Lax'));
    }
}
