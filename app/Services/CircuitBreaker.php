<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stops repeated calls to an unhealthy dependency and returns a safe fallback.
 */
final class CircuitBreaker
{
    public function run(string $name, Closure $operation, mixed $fallback = null): mixed
    {
        $state = $this->state($name);
        $now = time();

        if ($state['open_until'] > $now) {
            Log::notice('Circuit breaker is open; dependency call skipped.', [
                'metric' => 'dependency.circuit_open',
                'dependency' => $name,
                'retry_at' => $state['open_until'],
            ]);

            return $this->resolveFallback($fallback, null);
        }

        try {
            $result = $operation();
            $this->reset($name);

            return $result;
        } catch (Throwable $exception) {
            $failures = $state['failures'] + 1;
            $thresholdRaw = config('observability.circuit_breaker.failure_threshold', 3);
            $threshold = max(1, is_numeric($thresholdRaw) ? (int) $thresholdRaw : 3);
            $openUntil = $failures >= $threshold
                ? $now + max(1, is_numeric(config('observability.circuit_breaker.cooldown_seconds', 60)) ? (int) config('observability.circuit_breaker.cooldown_seconds', 60) : 60)
                : 0;

            $this->saveState($name, [
                'failures' => $failures,
                'open_until' => $openUntil,
            ]);

            Log::warning('Dependency call failed; circuit breaker state updated.', [
                'metric' => 'dependency.call_failure',
                'dependency' => $name,
                'failures' => $failures,
                'open_until' => $openUntil,
                'exception' => $exception->getMessage(),
            ]);

            return $this->resolveFallback($fallback, $exception);
        }
    }

    /**
     * @return array{failures: int, open_until: int}
     */
    private function state(string $name): array
    {
        try {
            $state = Cache::get($this->key($name), ['failures' => 0, 'open_until' => 0]);

            if (is_array($state) && isset($state['failures'], $state['open_until'])) {
                return [
                    'failures' => is_numeric($state['failures']) ? (int) $state['failures'] : 0,
                    'open_until' => is_numeric($state['open_until']) ? (int) $state['open_until'] : 0,
                ];
            }
        } catch (Throwable $exception) {
            Log::warning('Circuit breaker state lookup failed; dependency call will proceed.', [
                'dependency' => $name,
                'exception' => $exception->getMessage(),
            ]);
        }

        return ['failures' => 0, 'open_until' => 0];
    }

    /**
     * @param  array{failures: int, open_until: int}  $state
     */
    private function saveState(string $name, array $state): void
    {
        try {
            Cache::put($this->key($name), $state, now()->addSeconds(3600));
        } catch (Throwable $exception) {
            Log::warning('Circuit breaker state update failed.', [
                'dependency' => $name,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function reset(string $name): void
    {
        try {
            Cache::forget($this->key($name));
        } catch (Throwable $exception) {
            Log::warning('Circuit breaker reset failed.', [
                'dependency' => $name,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function key(string $name): string
    {
        return 'circuit-breaker:'.$name;
    }

    private function resolveFallback(mixed $fallback, ?Throwable $exception): mixed
    {
        return $fallback instanceof Closure ? $fallback($exception) : $fallback;
    }
}
