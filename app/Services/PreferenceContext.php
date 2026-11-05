<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Holds the preferences a queued job must format with, because a worker has no
 * request, session or authenticated user for `PreferencesService::current()` to
 * read. Registered as a singleton so every `LocalizationService` instance inside
 * the same job sees it, and cleared before every job by
 * `AppServiceProvider::resetRequesterContextForQueuedJobs()` so it cannot leak
 * from one job into the next.
 */
final class PreferenceContext
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $preferences = null;

    /**
     * @param  array<string, mixed>  $preferences
     */
    public function set(array $preferences): void
    {
        $this->preferences = $preferences;
    }

    public function clear(): void
    {
        $this->preferences = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        return $this->preferences;
    }
}
