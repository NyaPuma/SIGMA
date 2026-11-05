<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * Localization presentation service.
 *
 * Keeps the formatting API separate from locale preference resolution. All
 * formatters honour the active user preferences (date/time format, number
 * separators and currency) resolved via `PreferencesService`, while
 * preserving a locale-based fallback when no explicit user choice exists.
 */
final class LocalizationService
{
    /**
     * Default number-format sentinel stored by the preferences migration.
     * When the stored value equals this we treat it as "no explicit choice"
     * and fall back to the language's natural grouping, avoiding a rendering
     * regression for locales whose separators differ (e.g. pt-PT).
     */
    private const DEFAULT_NUMBER_FORMAT = ['decimal' => '.', 'thousand' => ','];

    public function formatNumber(float $value, ?string $locale = null): string
    {
        $prefs = $this->preferences();
        $seps = $this->numberSeparators($prefs);

        if ($seps !== null) {
            return number_format($value, 0, $seps['decimal'], $seps['thousand']);
        }

        return LocaleService::formatNumber($value, 0, $locale);
    }

    public function formatDecimal(float $value, int $decimals = 2, ?string $locale = null): string
    {
        $prefs = $this->preferences();
        $seps = $this->numberSeparators($prefs);

        if ($seps !== null) {
            return number_format($value, $decimals, $seps['decimal'], $seps['thousand']);
        }

        return LocaleService::formatNumber($value, $decimals, $locale);
    }

    /**
     * Formats a monetary amount in the user's preferred currency.
     *
     * Stored amounts are assumed to be in the provider base currency (EUR);
     * the value is converted into the user's preferred currency when they
     * differ, then formatted with the user's number separators (or the
     * locale's natural grouping when none are chosen).
     */
    public function formatCurrency(float $value, ?string $locale = null): string
    {
        $prefs = $this->preferences();
        $targetLocale = $locale ?? app()->getLocale();

        $prefCurrency = $prefs['currency'] ?? 'EUR';
        $userCurrency = is_string($prefCurrency) ? strtoupper($prefCurrency) : 'EUR';
        $amount = $value;

        if ($userCurrency !== CurrencyRateService::BASE_CURRENCY) {
            $amount = app(CurrencyRateService::class)->convert(
                (float) $value,
                CurrencyRateService::BASE_CURRENCY,
                $userCurrency
            );
        }

        $seps = $this->numberSeparators($prefs);

        if ($seps !== null) {
            $formattedNum = number_format($amount, 2, $seps['decimal'], $seps['thousand']);
        } else {
            $formattedNum = LocaleService::formatNumber($amount, 2, $targetLocale);
        }

        $symbol = LocaleService::currencySymbol($userCurrency);

        if (in_array($targetLocale, ['en-US', 'en-GB'], true)) {
            return "{$symbol}{$formattedNum}";
        }

        return "{$formattedNum} {$symbol}";
    }

    /**
     * Formats a date honouring the user's exact date-format string (e.g.
     * `d-m-Y` is always rendered as `dd-mm-yyyy`, never `dd/mm/yyyy`).
     */
    public function formatDate(mixed $date, string $format = 'short', ?string $locale = null): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $parsed = $this->parseDate($date);

        if ($parsed === null) {
            return '';
        }

        $date = $parsed;

        $prefs = $this->preferences();
        $userFormat = $prefs['date_format'] ?? null;

        if (is_string($userFormat) && $userFormat) {
            $rendered = $date->format($userFormat);

            return $rendered;
        }

        return $this->formatDateFallback($date, $format, $locale);
    }

    /**
     * Formats date and time honouring the user's date + time format strings.
     */
    public function formatDateTime(mixed $date, ?string $locale = null): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $date = $this->parseDate($date);

        if ($date === null) {
            return '';
        }

        $prefs = $this->preferences();
        $userDate = $prefs['date_format'] ?? null;
        $userTime = $prefs['time_format'] ?? null;

        if (is_string($userDate) && $userDate !== '') {
            $time = is_string($userTime) && $userTime !== '' ? $userTime : 'H:i';
            $rendered = $date->format(trim($userDate.' '.$time));

            if ($rendered !== '') {
                return $rendered;
            }
        }

        return $this->formatDateTimeFallback($date, $locale);
    }

    public function formatPercent(float $value, ?string $locale = null): string
    {
        $prefs = $this->preferences();
        $seps = $this->numberSeparators($prefs);

        if ($seps !== null) {
            return number_format($value, 1, $seps['decimal'], $seps['thousand']).'%';
        }

        return LocaleService::formatPercent($value, 1, $locale);
    }

    /**
     * @return array{value: float, unit: string, formatted: string}
     */
    public function convertUnit(float $value, string $type, string $fromUnit = '', ?string $locale = null): array
    {
        return LocaleService::convertUnit($value, $type, $fromUnit, $locale);
    }

    private function parseDate(mixed $date): ?Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        if ($date instanceof \DateTimeInterface) {
            return Carbon::instance($date);
        }

        if (! is_string($date) && ! is_int($date) && ! is_float($date)) {
            return null;
        }

        try {
            return Carbon::parse((string) $date);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolves the active user preferences without requiring a `$request`
     * argument on every call, falling back to defaults in CLI contexts.
     *
     * @return array<string, mixed>
     */
    private function preferences(): array
    {
        // A queued job stores the requester's row here, since no request exists.
        $context = app(PreferenceContext::class)->get();

        if (is_array($context)) {
            return $context;
        }

        $request = app()->bound('request') ? app('request') : null;

        if ($request) {
            $merged = $request->_preferences ?? null;

            if (is_array($merged)) {
                /** @var array<string, mixed> $merged */
                return $merged;
            }

            // Resolve once and cache back onto the request so repeated format
            // calls within the same request do not re-query the database.
            $prefs = PreferencesService::current($request);
            $request->merge(['_preferences' => $prefs]);

            return $prefs;
        }

        return [
            'language' => 'pt-PT',
            'currency' => 'EUR',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'number_format' => '{"decimal":".","thousand":","}',
        ];
    }

    /**
     * Decodes the user's number separators, or null when no explicit choice
     * has been made (stored value is the default sentinel), so the formatter
     * can fall back to the language's natural grouping.
     *
     * @param  array<string, mixed>  $prefs
     * @return array{decimal: string, thousand: string}|null
     */
    private function numberSeparators(array $prefs): ?array
    {
        $raw = $prefs['number_format'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! isset($decoded['decimal'], $decoded['thousand'])
            || ! is_string($decoded['decimal']) || ! is_string($decoded['thousand'])) {
            return null;
        }

        $decimal = $decoded['decimal'];
        $thousand = $decoded['thousand'];

        if ($decimal === self::DEFAULT_NUMBER_FORMAT['decimal']
            && $thousand === self::DEFAULT_NUMBER_FORMAT['thousand']) {
            return null;
        }

        return ['decimal' => $decimal, 'thousand' => $thousand];
    }

    private function formatDateFallback(Carbon $date, string $format, ?string $locale): string
    {
        $targetLocale = $locale ?? app()->getLocale();

        if (class_exists(\IntlDateFormatter::class)) {
            $dateType = match ($format) {
                'long' => \IntlDateFormatter::LONG,
                'medium' => \IntlDateFormatter::MEDIUM,
                default => \IntlDateFormatter::SHORT,
            };

            $formatter = \IntlDateFormatter::create(
                $targetLocale,
                $dateType,
                \IntlDateFormatter::NONE,
                $date->getTimezone()->getName(),
            );

            if ($formatter) {
                $result = $formatter->format($date);
                if ($result !== false) {
                    return $result;
                }
            }
        }

        if ($format === 'short') {
            return match ($targetLocale) {
                'en-US' => $date->format('m/d/Y'),
                'ja-JP', 'zh-CN', 'ko-KR' => $date->format('Y/m/d'),
                default => $date->format('d/m/Y'),
            };
        }

        $localized = $date->locale($targetLocale);

        if (! $localized instanceof Carbon) {
            return $date->format($format === 'long' ? 'j F Y' : 'j M Y');
        }

        return $localized->translatedFormat($format === 'long' ? 'j F Y' : 'j M Y');
    }

    private function formatDateTimeFallback(Carbon $date, ?string $locale): string
    {
        $targetLocale = $locale ?? app()->getLocale();

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = \IntlDateFormatter::create(
                $targetLocale,
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::SHORT,
                $date->getTimezone()->getName(),
            );

            if ($formatter) {
                $result = $formatter->format($date);
                if ($result !== false) {
                    return $result;
                }
            }
        }

        return $this->formatDateFallback($date, 'short', $targetLocale).' '.$date->format('H:i');
    }
}
