<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Support\Facades\Auth;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Adds request and authenticated-user context to structured log records.
 */
final class RequestContextProcessor implements ProcessorInterface
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'secret',
        'authorization',
        'cookie',
        'credit_card',
        'card_number',
        'cvv',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->requestContext();
        /** @var array<string, mixed> $mergedInput */
        $mergedInput = array_merge($record->context, $context);
        $merged = $this->sanitize($mergedInput);

        return $record->with(context: $merged);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestContext(): array
    {
        $context = [
            'request_id' => null,
            'user_id' => null,
            'ip_address' => null,
            'http_method' => null,
            'route' => null,
            'execution_time_ms' => null,
        ];

        if (! app()->bound('request')) {
            return $context;
        }

        $request = request();
        $context['request_id'] = $request->attributes->get('request_id');
        $context['ip_address'] = $request->ip();
        $context['http_method'] = $request->method();
        $context['route'] = $request->route()?->uri();
        $context['execution_time_ms'] = $request->attributes->get('execution_time_ms');

        try {
            $context['user_id'] = Auth::id();
        } catch (\Throwable) {
            // Authentication may not be available during early application boot.
        }

        if ($context['execution_time_ms'] === null) {
            $startedAt = $request->attributes->get('request_started_at');

            if (is_float($startedAt)) {
                $context['execution_time_ms'] = round((microtime(true) - $startedAt) * 1000, 2);
            }
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $sanitized[$key] = $this->sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
