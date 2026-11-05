<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Audit;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Room;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserProfile;
use App\Observers\AuditObserver;
use App\Observers\TicketObserver;
use App\Observers\UserObserver;
use App\Policies\EquipmentPolicy;
use App\Policies\MaintenancePlanPolicy;
use App\Policies\PartCategoryPolicy;
use App\Policies\PartPolicy;
use App\Policies\RoomPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TaxRatePolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use App\Policies\UserProfilePolicy;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\EquipmentRepository;
use App\Repositories\RoomRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UserRepository;
use App\Services\AIService;
use App\Services\AnalyticsService;
use App\Services\LocalizationService;
use App\Services\NotificationService;
use App\Services\PreferenceContext;
use App\Services\SystemSettingsService;
use App\Services\TicketStatusService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Sentry\Event;
use Sentry\State\HubInterface;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Registers all services and contract bindings in the application.
     */
    public function register(): void
    {
        // Bind interfaces to concrete repositories
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(TicketRepositoryInterface::class, TicketRepository::class);
        $this->app->bind(EquipmentRepositoryInterface::class, EquipmentRepository::class);
        $this->app->bind(RoomRepositoryInterface::class, RoomRepository::class);

        // Register domain services as singletons
        $this->app->singleton(TicketStatusService::class);
        $this->app->singleton(AnalyticsService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(AIService::class);
        $this->app->singleton(SystemSettingsService::class);
        $this->app->singleton(PreferenceContext::class);
    }

    /**
     * Bootstraps any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerObservers();
        $this->registerSlowQueryListener();
        $this->registerQueueObservability();
        $this->registerFormattingDirectives();
        $this->registerSentrySanitization();

        // Apply overrides saved in the Settings → Configuration page
        $this->app->make(SystemSettingsService::class)->applyOverrides();

        // Captured after the overrides so the default is the configured one, not a
        // locale a previous job happened to leave behind.
        $this->resetRequesterContextForQueuedJobs();
    }

    /**
     * Registers authorization policies for the models.
     */
    private function registerPolicies(): void
    {
        // Laravel Pulse dashboard is restricted to administrators. In non-local
        // environments Life just overrides Pulse's default local-only ability.
        Gate::define('viewPulse', fn (?User $user = null) => $user?->isAdmin() === true);

        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Equipment::class, EquipmentPolicy::class);
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(UserProfile::class, UserProfilePolicy::class);
        Gate::policy(Part::class, PartPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(TaxRate::class, TaxRatePolicy::class);
        Gate::policy(PartCategory::class, PartCategoryPolicy::class);
        Gate::policy(MaintenancePlan::class, MaintenancePlanPolicy::class);
    }

    /**
     * Registers localized formatting directives used in views.
     */
    private function registerFormattingDirectives(): void
    {
        $service = LocalizationService::class;

        Blade::directive('money', static fn (string $expr) => "<?php echo app({$service}::class)->formatCurrency($expr); ?>");
        Blade::directive('number', static fn (string $expr) => "<?php echo app({$service}::class)->formatNumber($expr); ?>");
        Blade::directive('percent', static fn (string $expr) => "<?php echo app({$service}::class)->formatPercent($expr); ?>");
        Blade::directive('date', static fn (string $expr) => "<?php echo app({$service}::class)->formatDate($expr); ?>");
        Blade::directive('datetime', static fn (string $expr) => "<?php echo app({$service}::class)->formatDateTime($expr); ?>");

        // @localized* directives
        Blade::directive('localizedDate', static fn (string $expr) => "<?php echo app({$service}::class)->formatDate($expr); ?>");
        Blade::directive('localizedDateTime', static fn (string $expr) => "<?php echo app({$service}::class)->formatDateTime($expr); ?>");
        Blade::directive('localizedNumber', static fn (string $expr) => "<?php echo app({$service}::class)->formatNumber($expr); ?>");
        Blade::directive('localizedCurrency', static fn (string $expr) => "<?php echo app({$service}::class)->formatCurrency($expr); ?>");
        Blade::directive('localizedUnit', static fn (string $expr) => "<?php echo app({$service}::class)->convertUnit($expr)['formatted']; ?>");
    }

    /**
     * Registers observers responsible for listening to model lifecycle events.
     */
    private function registerObservers(): void
    {
        Ticket::observe(TicketObserver::class);
        User::observe(UserObserver::class);
        Audit::observe(AuditObserver::class);
    }

    /**
     * Registers a listener to detect and log slow SQL queries.
     */
    private function registerSlowQueryListener(): void
    {
        if (! config('database.connections.mysql.slow_query_log', false)) {
            return;
        }

        $thresholdMsRaw = config('database.connections.mysql.slow_query_threshold_ms', 100);
        $thresholdMs = is_numeric($thresholdMsRaw) ? (float) $thresholdMsRaw : 100.0;

        DB::listen(function (QueryExecuted $query) use ($thresholdMs): void {
            if ($query->time >= $thresholdMs) {
                Log::warning('Slow database query detected', [
                    'metric' => 'database.query.duration_ms',
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'duration_ms' => round((float) $query->time, 2),
                ]);
            }
        });
    }

    /**
     * A worker keeps one application instance for every job it runs, so the
     * requester's locale and formatting preferences an export job applied would
     * leak into the next job and its notifications. Each job starts again from the
     * configured default with no requester context at all.
     */
    private function resetRequesterContextForQueuedJobs(): void
    {
        // `App::setLocale()` rewrites `config('app.locale')` too, so the baseline is
        // read once here instead of inside the listener.
        $default = App::getLocale();
        $preferences = $this->app->make(PreferenceContext::class);

        Queue::before(function () use ($default, $preferences): void {
            App::setLocale($default);
            $preferences->clear();
        });
    }

    /**
     * Registers queue timing and failure telemetry.
     */
    private function registerQueueObservability(): void
    {
        $startedAt = [];

        Queue::before(function (JobProcessing $event) use (&$startedAt): void {
            $startedAt[(string) $event->job->getJobId()] = microtime(true);
        });

        Queue::after(function (JobProcessed $event) use (&$startedAt): void {
            $jobId = (string) $event->job->getJobId();
            $started = $startedAt[$jobId] ?? null;
            unset($startedAt[$jobId]);

            if (! is_float($started)) {
                return;
            }

            $durationMs = round((microtime(true) - $started) * 1000, 2);

            $threshold = config('observability.queue_slow_job_threshold_ms', 1000);
            if ($durationMs >= (is_numeric($threshold) ? (float) $threshold : 1000.0)) {
                Log::warning('Slow queue job detected', [
                    'metric' => 'queue.job.duration_ms',
                    'job' => $event->job->resolveName(),
                    'job_id' => $jobId,
                    'duration_ms' => $durationMs,
                ]);
            }
        });

        Queue::failing(function (JobFailed $event): void {
            Log::error('Queue job failed', [
                'metric' => 'queue.job.failure',
                'job' => $event->job->resolveName(),
                'job_id' => (string) $event->job->getJobId(),
                'exception' => $event->exception->getMessage(),
            ]);

            try {
                \Sentry\captureException($event->exception);
            } catch (\Throwable $reportingException) {
                Log::warning('Sentry queue failure reporting failed', [
                    'exception' => $reportingException->getMessage(),
                ]);
            }
        });
    }

    /**
     * Redacts sensitive data (passwords, tokens, keys, cards) from Sentry events.
     *
     * Historically this sanitization lived as a `before_send` closure in
     * `config/sentry.php`, which broke `php artisan config:cache` (closures are
     * not serializable). It is now applied to the Sentry client options once the
     * hub is resolved, so the config stays fully serializable and the production
     * deploy can generate the caches.
     */
    private function registerSentrySanitization(): void
    {
        $this->app->afterResolving(HubInterface::class, function (HubInterface $hub): void {
            try {
                $client = $hub->getClient();
            } catch (\Throwable $e) {
                Log::debug('Sentry sanitization setup skipped', ['error' => $e->getMessage()]);

                return;
            }

            if ($client === null) {
                return;
            }

            $sanitize = static function (mixed $value) use (&$sanitize): mixed {
                if (! is_array($value)) {
                    return $value;
                }

                $result = [];

                foreach ($value as $key => $item) {
                    $keyString = strtolower((string) $key);

                    if (preg_match('/password|token|secret|api[_-]?key|authorization|card|cvv/', $keyString) === 1) {
                        $result[$key] = '[REDACTED]';
                    } else {
                        $result[$key] = $sanitize($item);
                    }
                }

                return $result;
            };

            $client->getOptions()->setBeforeSendCallback(static function (Event $event) use ($sanitize): Event {
                $request = $sanitize($event->getRequest());

                if (is_array($request)) {
                    /** @var array<string, mixed> $request */
                    $event->setRequest($request);
                }

                foreach ($event->getContexts() as $name => $context) {
                    $sanitizedContext = $sanitize($context);

                    if (is_array($sanitizedContext)) {
                        /** @var array<string, mixed> $sanitizedContext */
                        $event->setContext($name, $sanitizedContext);
                    }
                }

                $extra = $sanitize($event->getExtra());

                if (is_array($extra)) {
                    /** @var array<string, mixed> $extra */
                    $event->setExtra($extra);
                }

                return $event;
            });
        });
    }
}
