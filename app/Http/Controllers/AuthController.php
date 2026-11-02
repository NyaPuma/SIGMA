<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

final class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * Authenticates a user based on the submitted credentials.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower($request->string('email')->toString());
        $password = $request->string('password')->toString();
        $rateLimitKey = "login_attempts:{$email}";

        $maxAttemptsRaw = config('services.custom.auth.max_attempts', 5);
        $lockoutMinutesRaw = config('services.custom.auth.lockout_minutes', 15);
        $maxAttempts = is_numeric($maxAttemptsRaw) ? (int) $maxAttemptsRaw : 5;
        $lockoutMinutes = is_numeric($lockoutMinutesRaw) ? (int) $lockoutMinutesRaw : 15;

        // 1. Check the login attempt rate limit (Rate Limiting via Cache)
        $attemptsRaw = Cache::get($rateLimitKey, 0);
        $attempts = is_numeric($attemptsRaw) ? (int) $attemptsRaw : 0;

        if ($attempts >= $maxAttempts) {
            return response()->json([
                'message' => __('common.Conta temporariamente bloqueada. Tente novamente mais tarde.'),
            ], 429)->header('Retry-After', (string) ($lockoutMinutes * 60));
        }

        // 2. Find the active user
        $user = User::where('email', $email)
            ->where('active', true)
            ->first();

        // 3. Validate credentials
        $valid = $user && Hash::check($password, $user->password);

        if (! $valid) {
            // Atomic increment: Cache::add seeds the key with TTL on first failure;
            // Cache::increment ensures correct count under concurrency.
            if (! Cache::add($rateLimitKey, 1, now()->addMinutes($lockoutMinutes))) {
                Cache::increment($rateLimitKey);
            }

            return response()->json([
                'message' => __('common.Credenciais inválidas.'),
            ], 401);
        }

        // 4. Clear failed attempts on success
        Cache::forget($rateLimitKey);

        // 5. Rehash password hash if needed (automatic re-hash)
        if (Hash::needsRehash($user->password)) {
            $user->password = Hash::make($password);
            $user->save();
        }

        // 6. Ensure profile exists and create session/api token
        $this->userService->ensureDefaultProfile($user);
        $plainToken = $this->userService->createToken($user, $request);

        return $this->userService->buildAuthResponse($user, $plainToken, $request);
    }

    /**
     * Terminates the authenticated user's session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $user->api_token = null;
        $user->setRememberToken('');
        $user->save();

        return $this->userService->buildLogoutResponse($request);
    }
}
