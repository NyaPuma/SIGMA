<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Services\LocaleService;
use App\Services\PreferenceContext;
use App\Services\PreferencesService;
use Illuminate\Support\Facades\App;

/**
 * Shared by the export jobs, which run in a worker where no request, session or
 * authentication exists: nothing would otherwise restore the language, currency
 * or date format of the user who asked for the file.
 *
 * `AppServiceProvider::resetRequesterContextForQueuedJobs()` clears both again
 * before every job, because a worker reuses one application instance.
 */
trait SetsRequesterContext
{
    protected function applyRequesterContext(int $userId): void
    {
        $preferences = PreferencesService::forUserId($userId);

        // Same validation SetLocaleMiddleware applies: an unknown or unsupported value is
        // ignored instead of switching the worker to a locale with no translation files.
        if (LocaleService::isSupported($preferences['language'])) {
            App::setLocale(LocaleService::sanitize($preferences['language']));
        }

        app(PreferenceContext::class)->set($preferences);
    }
}
