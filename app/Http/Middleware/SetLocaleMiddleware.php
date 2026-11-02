<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthUserResolver;
use App\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class SetLocaleMiddleware
{
    /**
     * Default locale applied when no preference is found.
     */
    public const DEFAULT_LOCALE = 'pt-PT';

    /**
     * Resolves the locale to use in the request and sets it on the application.
     *
     * Precedence: session → cookie → authenticated user's DB →
     * browser's `Accept-Language` → default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        return $response;
    }

    /**
     * Resolves the preferred locale from a request (also used in error handlers
     * and routes outside the web group).
     */
    public static function resolveFromRequest(Request $request): string
    {
        $fromSession = self::sessionLocale($request);
        $fromCookie = self::cookieLocale($request);

        if ($fromSession !== null) {
            return LocaleService::sanitize($fromSession);
        }

        if ($fromCookie !== null) {
            return LocaleService::sanitize($fromCookie);
        }

        return LocaleService::resolveFromRequest($request);
    }

    /**
     * Resolves the full preference, including the user's DB locale.
     */
    private function resolveLocale(Request $request): string
    {
        $fromSession = self::sessionLocale($request);

        if ($fromSession !== null) {
            return LocaleService::sanitize($fromSession);
        }

        $fromUser = $this->authenticatedUserLocale($request);

        if ($fromUser !== null) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $fromUser);
            }

            return $fromUser;
        }

        $fromCookie = self::cookieLocale($request);

        if ($fromCookie !== null) {
            return LocaleService::sanitize($fromCookie);
        }

        return LocaleService::resolveFromRequest($request);
    }

    /**
     * Locale stored in the session, if present.
     */
    private static function sessionLocale(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $locale = $request->session()->get('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    /**
     * Locale stored in the cookie, if present.
     */
    private static function cookieLocale(Request $request): ?string
    {
        $locale = $request->cookie('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    /**
     * Preferred locale of the authenticated user (from `users.locale` column).
     *
     * The 'api' guard is not yet populated when this middleware runs
     * (the `CustomAuthMiddleware` belongs to the route group), so the user is
     * resolved from request tokens when necessary.
     */
    private function authenticatedUserLocale(Request $request): ?string
    {
        $user = Auth::user() ?? Auth::guard('api')->user();

        if ($user === null) {
            $user = AuthUserResolver::fromRequest($request);
        }

        if ($user === null || $user->locale === null || $user->locale === '') {
            return null;
        }

        if (! LocaleService::isSupported($user->locale)) {
            Log::debug('User locale not supported', ['locale' => $user->locale]);

            return null;
        }

        return LocaleService::sanitize($user->locale);
    }
}
