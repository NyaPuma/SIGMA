<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PreferencesService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to load user preferences (currency, date format, number format)
 * into the request.
 *
 * Locale resolution is handled exclusively by `SetLocaleMiddleware` which
 * runs earlier in the stack.  This middleware resolves the full preferences
 * object (via `PreferencesService::current()`) and attaches it as
 * `$request->_preferences` for controllers and views.
 */
final class SetUserPreferencesMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Locale is resolved exclusively by `SetLocaleMiddleware` which runs
     * before this middleware.  This middleware only loads the full set of
     * user preferences (currency, date format, etc.) into the request so
     * they are available to controllers and views.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Store current preferences in request for easy access
        $prefs = PreferencesService::current($request);
        $request->merge([
            '_preferences' => $prefs,
        ]);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
