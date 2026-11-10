<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->active) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => __('auth.Autenticação necessária.')], 401);
            }

            return redirect('/ui/login');
        }

        // Admin always allowed
        if ($user->isAdmin()) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                /** @var Response $response */
                $response = $next($request);

                return $response;
            }
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['message' => __('common.Acesso proibido para o seu perfil.')], 403);
        }

        return redirect('/ui')->with('error', __('common.Não tem permissões para aceder a esta página.'));
    }
}
