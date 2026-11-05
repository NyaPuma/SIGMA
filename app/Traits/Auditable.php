<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

trait Auditable
{
    /**
     * Cached user ID per request.
     * Note: Static cache per process; in long-running processes (queues, Octane),
     * reset with resetResolvedUserId() to ensure proper ID resolution.
     */
    private static ?int $resolvedUserId = null;

    /**
     * Reset cached user ID for long-running processes (queues, Octane).
     */
    public static function resetResolvedUserId(): void
    {
        self::$resolvedUserId = null;
    }

    /**
     * Register Eloquent model event listeners for automatic audit logging.
     */
    public static function bootAuditable(): void
    {
        // O esquema de `audits.event` aceita precisamente estes quatro valores
        // (ver 0001_01_01_000006_create_audits_table.php); sem 'restored' aqui, um
        // `$model->restore()` não deixava nenhum rasto na auditoria.
        foreach (['created', 'updated', 'deleted', 'restored'] as $event) {
            call_user_func([static::class, $event], function (Model $model) use ($event): void {
                try {
                    self::createAudit($model, $event);
                } catch (Throwable $e) {
                    Log::warning('Audit trail failed', [
                        'model' => get_class($model),
                        'event' => $event,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }
    }

    /**
     * Create an audit record for the given model instance and lifecycle event.
     */
    private static function createAudit(Model $model, string $event): void
    {
        /** @var Request|null $request */
        $request = null;
        if (function_exists('request')) {
            $request = request();
        }

        $userId = self::resolveUserId($request);

        $old = null;
        $new = null;

        if ($event === 'created') {
            $new = $model->getAttributes();
        } elseif ($event === 'deleted') {
            $old = $model->getOriginal();
        } else {
            // Fall back to the dirty attributes if the change set is empty.
            $changes = $model->getChanges() ?: $model->getDirty();
            if (! empty($changes)) {
                $oldVals = [];
                $newVals = [];
                foreach ($changes as $k => $v) {
                    $oldVals[$k] = $model->getOriginal($k);
                    $newVals[$k] = $v;
                }
                $old = $oldVals;
                $new = $newVals;
            }
        }

        Audit::create([
            'user_id' => $userId,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Resolve and cache the user ID associated with the current HTTP request or API token.
     */
    private static function resolveUserId(?Request $request): ?int
    {
        if (self::$resolvedUserId !== null) {
            return self::$resolvedUserId;
        }

        $userId = null;

        if (function_exists('auth')) {
            $auth = auth();
            $authId = null;

            // Prefer the id() helper if available to avoid calling user()
            if (method_exists($auth, 'id')) {
                $authId = $auth->id();
            }

            // Fallback to user() when id() is not available
            if ($authId === null && method_exists($auth, 'user')) {
                $authUser = $auth->user();
                if (is_object($authUser)) {
                    if (property_exists($authUser, 'id')) {
                        $authId = $authUser->id;
                    } elseif (method_exists($authUser, 'getKey')) {
                        $authId = $authUser->getKey();
                    }
                }
            }

            if ($authId !== null) {
                $userId = $authId;
            }
        }

        if ($userId === null && $request) {
            $token = $request->header('X-Auth-Token') ?: $request->bearerToken();
            if (is_string($token) && $token !== '') {
                $hashedToken = User::hashToken($token);
                /** @var int|null $userId */
                $userId = User::where('api_token', $hashedToken)->value('id');
            }
        }

        self::$resolvedUserId = is_numeric($userId) ? (int) $userId : null;

        return self::$resolvedUserId;
    }
}
