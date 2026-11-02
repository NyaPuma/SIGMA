<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RateLimitMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly RateLimiter $limiter
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next,
        string $maxAttempts = '60',
        int $decayMinutes = 1
    ): Response {
        $maxAttemptsInt = (int) $maxAttempts;
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $maxAttemptsInt)) {
            return $this->buildResponse($key, $maxAttemptsInt, $decayMinutes);
        }

        // Record the current attempt in the rate limiter
        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttemptsInt,
            $this->calculateRemainingAttempts($key, $maxAttemptsInt)
        );
    }

    /**
     * Resolves the unique request signature for rate limiting purposes.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        if ($this->isAuthEndpoint($request)) {
            $email = (string) $request->input('email', '');

            return sha1($request->ip().'|'.$email);
        }

        // For other endpoints, use IP + user_id (if authenticated) or 'guest'
        $user = $request->user();
        $userId = $user ? (string) $user->id : 'guest';

        return sha1($request->ip().'|'.$userId.'|'.$request->path());
    }

    /**
     * Determines whether the request targets an authentication endpoint
     * (login, registration, or password recovery) where the
     * limit should be applied per IP + email.
     */
    protected function isAuthEndpoint(Request $request): bool
    {
        if ($request->is('login', 'register')) {
            return true;
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        return $routeName === 'api.login' || Str::startsWith($routeName, 'api.password.');
    }

    /**
     * Adds informative rate limit headers to the response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);

        return $response;
    }

    /**
     * Calculates the number of remaining allowed attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - (int) $this->limiter->attempts($key));
    }

    /**
     * Builds the response to return when the rate limit is exceeded.
     */
    protected function buildResponse(string $key, int $maxAttempts, int $decayMinutes): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        $response = response()->json([
            'message' => __('common.Demasiadas tentativas. Tente novamente mais tarde.'),
            'retry_after' => $retryAfter,
        ], 429);

        $response->headers->set('Retry-After', (string) $retryAfter);

        return $this->addHeaders($response, $maxAttempts, 0);
    }
}
