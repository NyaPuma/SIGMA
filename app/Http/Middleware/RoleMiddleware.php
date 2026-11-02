<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RoleMiddleware
{
    /**
     * Handle an incoming request and delegate to the next middleware in the chain.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        // 1. Check if the user is authenticated and active
        if (! $user || ! $user->active) {
            return $this->handleUnauthenticated($request);
        }

        // 2. Ensure the user has a valid profile assigned
        if (! $user->profile_id || ! $user->profile?->name) {
            return $this->handleInvalidProfile($request);
        }

        // 3. Check if the user's role is authorized for the route
        if (! in_array($user->profile->name, $roles, true)) {
            return $this->handleForbidden($request);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    /**
     * Handles the unauthenticated or inactive user scenario.
     */
    private function handleUnauthenticated(Request $request): Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => __('auth.Autenticação necessária.'),
            ], 401);
        }

        if ($request->cookies->has('api_token')) {
            return redirect('/ui/login')->withCookie(cookie()->forget('api_token'));
        }

        return redirect('/ui/login');
    }

    /**
     * Handles the invalid or missing user profile scenario.
     */
    private function handleInvalidProfile(Request $request): Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => __('validation.Perfil inválido.'),
            ], 403);
        }

        return redirect('/ui/login');
    }

    /**
     * Handles the forbidden access scenario due to insufficient permissions.
     */
    private function handleForbidden(Request $request): Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => __('common.Acesso proibido para o seu perfil.'),
            ], 403);
        }

        return redirect('/ui')->with('error', __('common.Não tem permissões para aceder a esta página.'));
    }
}
