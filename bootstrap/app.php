<?php

declare(strict_types=1);

use App\Http\Middleware\CustomAuthMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\RequestContextMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocaleMiddleware;
use App\Http\Middleware\SetUserPreferencesMiddleware;
use App\Jobs\CheckLowStockJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    // `configure()` already calls `withEvents()`, which registers the framework's own
    // EventServiceProvider. That provider discovers `app/Listeners` and registers every
    // listener a second time as `Class@handle`, on top of the explicit `$listen` map in
    // App\Providers\EventServiceProvider — so each queued listener ran (and each workflow
    // history row was written) twice. Discovery must be turned off here: the override of
    // `shouldDiscoverEvents()` in our own provider does not affect the framework one.
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestContextMiddleware::class);

        $middleware->web(append: [
            SetLocaleMiddleware::class,
            SetUserPreferencesMiddleware::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(append: [
            SetLocaleMiddleware::class,
            SecurityHeaders::class,
        ]);

        // Register aliases for clean usage in route files
        $middleware->alias([
            'custom.auth' => CustomAuthMiddleware::class,
            'role' => RoleMiddleware::class,
            'rate.limit' => RateLimitMiddleware::class,
        ]);

        $middleware->encryptCookies(except: [
            'api_token',
            'auth_token',
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // withoutOverlapping matches the guard the console.php schedules already use: a run
        // that outlives its slot (large stock tables) would otherwise stack on the next one.
        $schedule->job(new CheckLowStockJob)->dailyAt('06:00')->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidArgumentException $e) {
            // Domain rules raise these; vendor/framework ones must not leak internals in production.
            $message = app()->environment('production')
                ? __('validation.Dados inválidos')
                : $e->getMessage();

            return response()->json(['message' => $message], 422);
        });

        // Ensures the locale (session/cookie/browser) is also set on error pages,
        // where the web group middleware no longer runs.
        $exceptions->render(function (Throwable $e, Request $request) {
            app()->setLocale(SetLocaleMiddleware::resolveFromRequest($request));
        });
    })->create();
