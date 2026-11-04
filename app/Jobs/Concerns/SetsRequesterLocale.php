<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Models\User;
use App\Services\LocaleService;
use Illuminate\Support\Facades\App;

/**
 * Shared by the export jobs, which run in a worker where no request, session or
 * authentication exists, so nothing restores the language of the user who asked
 * for the file.
 */
trait SetsRequesterLocale
{
    protected function applyRequesterLocale(int $userId): void
    {
        /** @var string|null $locale */
        $locale = User::query()->whereKey($userId)->value('locale');

        // Same validation SetLocaleMiddleware applies: an unknown or unsupported value is
        // ignored instead of switching the worker to a locale with no translation files.
        if (! is_string($locale) || $locale === '' || ! LocaleService::isSupported($locale)) {
            return;
        }

        App::setLocale(LocaleService::sanitize($locale));
    }
}
