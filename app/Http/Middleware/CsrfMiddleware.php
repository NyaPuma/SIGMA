<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Session\Session as SessionContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CsrfMiddleware
{
    public function __construct(
        private readonly SessionContract $session
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkipCsrfValidation($request)) {
            return $next($request);
        }

        $token = $this->getCsrfTokenFromRequest($request);

        if (! $token || ! $this->validateCsrfToken($token)) {
            return response()->json([
                'message' => __('validation.CSRF Token inválido ou expirado.'),
                'error_code' => 419,
                'errors' => [
                    '_token' => ['The CSRF token is invalid or has expired.'],
                ],
            ], 419);
        }

        $this->regenerateSessionId();

        return $next($request);
    }

    /**
     * Determines whether CSRF validation should be skipped for the current request.
     */
    protected function shouldSkipCsrfValidation(Request $request): bool
    {
        if ($request->isMethod('GET')) {
            return true;
        }

        if ($request->is('login', 'register')) {
            return true;
        }

        $route = $request->route();
        $routeName = $route ? ($route->getName() ?? '') : '';

        if (in_array($routeName, [
            'api.auth.login',
            'api.auth.logout',
            'api.user.profile.update',
            'api.ticket.create',
            'api.equipment.create',
            'api.notification.create',
        ], true)) {
            $token = $request->header('X-Auth-Token') ?: $request->bearerToken();

            if ($token && ! empty($this->session->get('_token'))) {
                return true;
            }
        }

        if ($routeName !== '' && Str::startsWith(strtolower($routeName), ['api.admin', 'api.analytics'])) {
            $token = $request->header('X-Admin-Token') ?: $request->bearerToken();

            if (! empty($token)) {
                return true;
            }
        }

        if ($routeName !== '' && Str::startsWith(strtolower($routeName), ['api.health', 'api.status'])) {
            return true;
        }

        return false;
    }

    /**
     * Extracts the CSRF token from the request (header or form field).
     * Never returns the session token as "provided": that would allow
     * any request with an active session to pass without presenting a token.
     */
    protected function getCsrfTokenFromRequest(Request $request): ?string
    {
        $headerToken = $request->header('X-CSRF-Token');

        if (! empty($headerToken)) {
            return $headerToken;
        }

        $inputToken = $request->input('_token');

        if (is_string($inputToken) && $inputToken !== '') {
            return $inputToken;
        }

        return null;
    }

    /**
     * Validates that the provided token matches the one stored in the session.
     * Uses hash_equals for constant-time comparison.
     */
    protected function validateCsrfToken(string $token): bool
    {
        $trimmedToken = trim($token);

        if (empty($trimmedToken)) {
            return false;
        }

        $stored = $this->session->get('_token');
        $storedToken = is_string($stored) ? $stored : '';

        if ($storedToken === '' || ! hash_equals($storedToken, $trimmedToken)) {
            Log::debug('CsrfMiddleware - Token mismatch', [
                'provided_token' => substr($trimmedToken, 0, 8).'...',
                'stored_token' => $storedToken ?: null,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Regenerates the session ID for additional security against fixation attacks.
     */
    protected function regenerateSessionId(): void
    {
        $token = $this->session->get('_token');

        if ($token && ! empty($this->session->getId())) {
            try {
                $this->session->regenerate();
            } catch (\Exception $e) {
                Log::error('CsrfMiddleware - Failed to regenerate session ID', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
