<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes a correlation identifier and emits slow-request telemetry.
 */
final class RequestContextMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = (string) ($request->headers->get('X-Request-ID') ?: Str::uuid());

        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('request_started_at', $startedAt);
        Log::withContext(['request_id' => $requestId]);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } finally {
            $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
            $request->attributes->set('execution_time_ms', $durationMs);

            $slowThreshold = config('observability.slow_request_threshold_ms', 100);
            $slowThreshold = is_numeric($slowThreshold) ? (float) $slowThreshold : 100.0;

            if ($durationMs >= $slowThreshold) {
                Log::warning('Slow HTTP request detected', [
                    'metric' => 'http.request.duration_ms',
                    'duration_ms' => $durationMs,
                    'status_code' => isset($response) ? $response->getStatusCode() : 500,
                ]);
            }

            $peakMemoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

            $memoryThreshold = config('observability.high_memory_threshold_mb', 128);
            $memoryThreshold = is_numeric($memoryThreshold) ? (float) $memoryThreshold : 128.0;

            if ($peakMemoryMb >= $memoryThreshold) {
                Log::warning('High request memory usage detected', [
                    'metric' => 'http.request.peak_memory_mb',
                    'peak_memory_mb' => $peakMemoryMb,
                ]);
            }

            Log::withoutContext();
        }
    }
}
