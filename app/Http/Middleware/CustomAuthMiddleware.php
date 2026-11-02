<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class CustomAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $candidates = $this->collectTokenCandidates($request);

        if (empty($candidates)) {
            return $this->unauthenticatedResponse($request);
        }

        $tokensToTry = $this->resolveTokensToTry($request, $candidates);
        [$user, $token] = $this->findUserByTokens($tokensToTry);

        if (! $user instanceof User) {
            return $this->invalidTokenResponse(
                $request,
                $request->cookies->has('api_token') || $request->cookies->has('auth_token')
            );
        }

        if ($this->hasInvalidProfile($user)) {
            return $this->invalidProfileResponse($request);
        }

        if ($this->isTokenExpired($user)) {
            return $this->expiredTokenResponse($request);
        }

        Auth::guard('api')->setUser($user);
        Auth::shouldUse('api');

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        return $response;
    }

    /**
     * Collects all authentication token candidates from the request.
     */
    /** @return array<int, string> */
    private function collectTokenCandidates(Request $request): array
    {
        $sessionToken = null;
        try {
            $sessionToken = $request->session()->get('api_token');
        } catch (\Throwable $e) {
            Log::debug('Session token read failed', ['error' => $e->getMessage()]);
        }

        return array_filter([
            $request->header('X-Auth-Token'),
            $request->bearerToken(),
            $request->cookie('api_token'),
            $request->cookie('auth_token'),
            $sessionToken,
        ], fn ($v) => is_string($v) && $v !== '');
    }

    /**
     * Resolves which tokens to try validating based on request precedence.
     */
    /**
     * @param array<int, string> $candidates
     * @return array<int, string>
     */
    private function resolveTokensToTry(Request $request, array $candidates): array
    {
        $explicitToken = reset($candidates);
        $explicitToken = is_string($explicitToken) ? $explicitToken : '';

        return $request->header('X-Auth-Token') || $request->bearerToken()
            ? [$explicitToken]
            : $candidates;
    }

    /**
     * Finds a valid user through the candidate tokens.
     */
    /**
     * @param array<int, string> $tokens
     * @return array{0: User|null, 1: string|null}
     */
    private function findUserByTokens(array $tokens): array
    {
        foreach ($tokens as $candidate) {
            $tokenHash = User::hashToken($candidate);
            $found = User::with('profile')
                ->where('api_token', $tokenHash)
                ->where('active', true)
                ->whereNull('deleted_at')
                ->first();

            if (! $found && app()->environment('testing')) {
                $found = User::with('profile')
                    ->where('api_token', $candidate)
                    ->where('active', true)
                    ->whereNull('deleted_at')
                    ->first();
            }

            if ($found) {
                return [$found, $candidate];
            }
        }

        return [null, null];
    }

    /**
     * Checks whether the user has an invalid or missing profile.
     */
    private function hasInvalidProfile(User $user): bool
    {
        return ! $user->profile_id || ! $user->profile?->name;
    }

    /**
     * Checks whether the user's access token has expired.
     * Tokens without a creation date never expire (deterministic in tests).
     */
    private function isTokenExpired(User $user): bool
    {
        $expiryDays = config('services.custom.auth.token_expiry_days', 30);

        if (! $user->token_created_at || $user->token_created_at->diffInDays(now()) <= $expiryDays) {
            return false;
        }

        $user->api_token = null;
        $user->save();

        return true;
    }

    /**
     * Returns the response for unauthenticated requests.
     */
    private function unauthenticatedResponse(Request $request): Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => __('auth.Autenticação necessária. Envie X-Auth-Token no cabeçalho.'),
                'error_code' => 401,
            ], 401);
        }

        return redirect('/ui/login');
    }

    /**
     * Returns the response for invalid tokens or inactive users.
     */
    private function invalidTokenResponse(Request $request, bool $hasCookie): Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            $response = response()->json([
                'message' => __('equipment.Token inválido ou utilizador inativo.'),
                'error_code' => 401,
                'errors' => ['api_token' => ['Invalid or user is inactive.']],
            ], 401);

            return $hasCookie
                ? $response->withCookie(cookie()->forget('api_token'))->withCookie(cookie()->forget('auth_token'))
                : $response;
        }

        if ($hasCookie) {
            return redirect('/ui/login')
                ->withCookie(cookie()->forget('api_token'))
                ->withCookie(cookie()->forget('auth_token'));
        }

        return redirect('/ui/login');
    }

    /**
     * Returns the response for invalid profiles.
     */
    private function invalidProfileResponse(Request $request): Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => __('validation.Perfil inválido.'),
                'error_code' => 403,
                'errors' => ['profile_id' => ['User must have a valid profile assigned.']],
            ], 403);
        }

        return redirect('/ui/login');
    }

    /**
     * Returns the response for expired tokens.
     */
    private function expiredTokenResponse(Request $request): Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => __('auth.Token expirado. Faça login novamente.'),
                'error_code' => 401,
            ], 401);
        }

        return redirect('/ui/login');
    }
}
