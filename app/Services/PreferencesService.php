<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Central user preferences service.
 *
 * Manages language, currency, and date format preferences independently.
 *
 * @phpstan-type UserPreferences array{
 *     language: string,
 *     currency: string,
 *     date_format: string,
 *     time_format: string,
 *     number_format: string
 * }
 */
final class PreferencesService
{
    /**
     * Session key for storing temporary preferences.
     */
    private const SESSION_KEY = 'user_preferences';

    /**
     * Default values.
     */
    private const DEFAULTS = [
        'language' => 'pt-PT',
        'currency' => 'EUR',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
        'number_format' => '{"decimal":".","thousand":",","example":"1,234.56"}',
    ];

    /**
     * Supported ISO 4217 currencies.
     */
    private const SUPPORTED_CURRENCIES = [
        'EUR', 'USD', 'GBP', 'BRL', 'JPY', 'CNY', 'PLN',
        'ARS', 'CAD', 'MXN', 'INR', 'AED', 'KRW', 'ALL',
        'AZN', 'BAM', 'BGN', 'BYN', 'CHF', 'CZK', 'DKK',
        'GEL', 'HUF', 'ISK', 'MDL', 'MKD', 'RON', 'RSD',
        'RUB', 'SEK', 'TRY', 'UAH', 'AMD',
    ];

    /**
     * Supported date formats.
     */
    private const SUPPORTED_DATE_FORMATS = [
        'd/m/Y',    // 31/12/2024
        'm/d/Y',    // 12/31/2024
        'Y-m-d',    // 2024-12-31
        'd-m-Y',    // 31-12-2024
        'Y/m/d',    // 2024/12/31
        'd.m.Y',    // 31.12.2024
        'm-d-Y',    // 12-31-2024
    ];

    /**
     * Supported time formats.
     */
    private const SUPPORTED_TIME_FORMATS = [
        'H:i' => ['label' => '24h', 'example' => '14:30'],
        'h:i A' => ['label' => '12h', 'example' => '2:30 PM'],
        'H:i:s' => ['label' => '24h + seconds', 'example' => '14:30:00'],
        'h:i:s A' => ['label' => '12h + seconds', 'example' => '2:30:00 PM'],
    ];

    /**
     * Supported number formats.
     * Each format is an array with: decimal_separator, thousand_separator
     */
    private const SUPPORTED_NUMBER_FORMATS = [
        'european' => ['decimal' => ',', 'thousand' => '.', 'example' => '1.234,56'],
        'english' => ['decimal' => '.', 'thousand' => ',', 'example' => '1,234.56'],
        'swiss' => ['decimal' => '.', 'thousand' => "'", 'example' => "1'234.56"],
        'spanish' => ['decimal' => ',', 'thousand' => ' ', 'example' => '1 234,56'],
        'indian' => ['decimal' => '.', 'thousand' => ',', 'example' => '1,23,456.78'],
        'chinese' => ['decimal' => '.', 'thousand' => '', 'example' => '1234.56'],
        'japanese' => ['decimal' => '.', 'thousand' => '', 'example' => '1234.56'],
        'none' => ['decimal' => '.', 'thousand' => '', 'example' => '1234.56'],
    ];

    /**
     * Gets preferences for the authenticated user.
     *
     * @return UserPreferences
     */
    public static function forUser(Authenticatable $user): array
    {
        // Read from the loaded model: `forUserId()` would query the same row back.
        $columnLocale = $user instanceof User ? $user->getAttribute('locale') : null;

        return self::withColumnLocale(self::storedForUserId(self::userId($user)), $columnLocale);
    }

    /**
     * Same lookup without needing an authenticated model, so a queue worker —
     * which has no request, session or guard — can format a report with the
     * preferences of the user who asked for it.
     *
     * @return UserPreferences
     */
    public static function forUserId(int $userId): array
    {
        return self::withColumnLocale(self::storedForUserId($userId), User::query()->whereKey($userId)->value('locale'));
    }

    /**
     * @return UserPreferences
     */
    private static function storedForUserId(int $userId): array
    {
        $prefs = UserPreference::where('user_id', $userId)->first();

        if ($prefs) {
            return [
                'language' => $prefs->language ?? self::DEFAULTS['language'],
                'currency' => $prefs->currency ?? self::DEFAULTS['currency'],
                'date_format' => $prefs->date_format ?? self::DEFAULTS['date_format'],
                'time_format' => $prefs->time_format ?? self::DEFAULTS['time_format'],
                'number_format' => $prefs->number_format ?? self::DEFAULTS['number_format'],
            ];
        }

        return self::DEFAULTS;
    }

    /**
     * `users.locale` is the language `SetLocaleMiddleware` applies to a request, and
     * both write paths keep it in step with `user_preferences.language`. It wins here
     * so a user whose preference row predates a later locale switch is not reverted.
     *
     * @param  UserPreferences  $preferences
     * @return UserPreferences
     */
    private static function withColumnLocale(array $preferences, mixed $columnLocale): array
    {
        if (is_string($columnLocale) && $columnLocale !== '' && LocaleService::isSupported($columnLocale)) {
            $preferences['language'] = $columnLocale;
        }

        return $preferences;
    }

    /**
     * Gets preferences from the session (for unauthenticated users).
     *
     * @return UserPreferences
     */
    public static function fromSession(Request $request): array
    {
        $sessionPrefs = $request->hasSession()
            ? $request->session()->get(self::SESSION_KEY)
            : null;

        if (is_array($sessionPrefs)) {
            return [
                'language' => self::stringPreference($sessionPrefs, 'language', self::DEFAULTS['language']),
                'currency' => self::stringPreference($sessionPrefs, 'currency', self::DEFAULTS['currency']),
                'date_format' => self::stringPreference($sessionPrefs, 'date_format', self::DEFAULTS['date_format']),
                'time_format' => self::stringPreference($sessionPrefs, 'time_format', self::DEFAULTS['time_format']),
                'number_format' => self::stringPreference($sessionPrefs, 'number_format', self::DEFAULTS['number_format']),
            ];
        }

        return self::DEFAULTS;
    }

    /**
     * @param  array<array-key, mixed>  $preferences
     */
    private static function stringPreference(array $preferences, string $key, string $default): string
    {
        $value = $preferences[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function userId(Authenticatable $user): int
    {
        $identifier = $user->getAuthIdentifier();

        return is_numeric($identifier) ? (int) $identifier : 0;
    }

    /**
     * Gets active preferences (authenticated user or session).
     *
     * Resolves the user via the web guard first, then falls back to
     * `AuthUserResolver` for api-token-authenticated users on web routes
     * (where `CustomAuthMiddleware` sets the user on the `api` guard only).
     *
     * @return UserPreferences
     */
    public static function current(Request $request): array
    {
        $user = $request->user()
            ?? Auth::guard('api')->user()
            ?? AuthUserResolver::fromRequest($request);

        if ($user) {
            return self::forUser($user);
        }

        return self::fromSession($request);
    }

    /**
     * Saves preferences for the authenticated user.
     *
     * @param  array<string, mixed>  $preferences
     */
    public static function saveForUser(Authenticatable $user, array $preferences): UserPreference
    {
        $validated = self::validatePreferences($preferences);

        return UserPreference::updateOrCreate(
            ['user_id' => self::userId($user)],
            $validated
        );
    }

    /**
     * Saves preferences to the session (for unauthenticated users).
     *
     * @param  array<string, mixed>  $preferences
     */
    public static function saveToSession(Request $request, array $preferences): void
    {
        $validated = self::validatePreferences($preferences);
        $request->session()->put(self::SESSION_KEY, $validated);
    }

    /**
     * Validates and normalizes preferences.
     *
     * @param  array<string, mixed>  $preferences
     * @return UserPreferences
     */
    public static function validatePreferences(array $preferences): array
    {
        return [
            'language' => self::validateLanguage(self::stringPreference($preferences, 'language', self::DEFAULTS['language'])),
            'currency' => self::validateCurrency(self::stringPreference($preferences, 'currency', self::DEFAULTS['currency'])),
            'date_format' => self::validateDateFormat(self::stringPreference($preferences, 'date_format', self::DEFAULTS['date_format'])),
            'time_format' => self::validateTimeFormat(self::stringPreference($preferences, 'time_format', self::DEFAULTS['time_format'])),
            'number_format' => self::validateNumberFormat(self::stringPreference($preferences, 'number_format', self::DEFAULTS['number_format'])),
        ];
    }

    /**
     * Validates the language.
     */
    public static function validateLanguage(string $language): string
    {
        if (LocaleService::isSupported($language)) {
            return $language;
        }

        return self::DEFAULTS['language'];
    }

    /**
     * Validates the currency.
     */
    public static function validateCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            return $currency;
        }

        return self::DEFAULTS['currency'];
    }

    /**
     * Validates the date format.
     */
    public static function validateDateFormat(string $format): string
    {
        if (in_array($format, self::SUPPORTED_DATE_FORMATS, true)) {
            return $format;
        }

        return self::DEFAULTS['date_format'];
    }

    /**
     * Validates the time format.
     */
    public static function validateTimeFormat(string $format): string
    {
        if (array_key_exists($format, self::SUPPORTED_TIME_FORMATS)) {
            return $format;
        }

        return self::DEFAULTS['time_format'];
    }

    /**
     * Gets the list of supported time formats.
     *
     * @return array<string, array{label: string, example: string}>
     */
    public static function supportedTimeFormats(): array
    {
        return self::SUPPORTED_TIME_FORMATS;
    }

    /**
     * Gets the list of supported currencies.
     */
    /**
     * @return list<string>
     */
    public static function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    /**
     * Gets the list of supported date formats.
     *
     * @return list<string>
     */
    public static function supportedDateFormats(): array
    {
        return self::SUPPORTED_DATE_FORMATS;
    }

    /**
     * Supported date formats grouped by separator symbol.
     *
     * @return array<string, list<string>>
     */
    public static function groupedDateFormats(): array
    {
        $grouped = [];

        foreach (self::SUPPORTED_DATE_FORMATS as $format) {
            $separator = 'other';

            foreach (['/', '-', '.'] as $symbol) {
                if (str_contains($format, $symbol)) {
                    $separator = $symbol;
                    break;
                }
            }

            $grouped[$separator][] = $format;
        }

        return $grouped;
    }

    /**
     * Gets the active currency for the user.
     */
    public static function getCurrency(Request $request): string
    {
        return self::current($request)['currency'];
    }

    /**
     * Gets the active date format for the user.
     */
    public static function getDateFormat(Request $request): string
    {
        return self::current($request)['date_format'];
    }

    /**
     * Gets the active time format for the user.
     */
    public static function getTimeFormat(Request $request): string
    {
        return self::current($request)['time_format'];
    }

    /**
     * Gets the active language for the user.
     */
    public static function getLanguage(Request $request): string
    {
        return self::current($request)['language'];
    }

    /**
     * Gets the active number format for the user.
     */
    public static function getNumberFormat(Request $request): string
    {
        return self::current($request)['number_format'];
    }

    /**
     * Gets number separators from the stored format.
     *
     * @return array{decimal: string, thousand: string}
     */
    public static function getNumberSeparators(Request $request): array
    {
        $format = self::getNumberFormat($request);
        $decoded = json_decode($format, true);

        if (is_array($decoded) && isset($decoded['decimal'], $decoded['thousand'])
            && is_string($decoded['decimal']) && is_string($decoded['thousand'])) {
            return [
                'decimal' => $decoded['decimal'],
                'thousand' => $decoded['thousand'],
            ];
        }

        return [
            'decimal' => '.',
            'thousand' => ',',
        ];
    }

    /**
     * Gets the list of supported number formats.
     *
     * @return array<string, array{decimal: string, thousand: string, example: string}>
     */
    public static function supportedNumberFormats(): array
    {
        return self::SUPPORTED_NUMBER_FORMATS;
    }

    /**
     * Supported number formats grouped by decimal separator.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public static function groupedNumberFormats(): array
    {
        $grouped = [];

        foreach (self::SUPPORTED_NUMBER_FORMATS as $key => $format) {
            $decimal = $format['decimal'];
            $grouped[$decimal][$key] = $format;
        }

        return $grouped;
    }

    /**
     * Formats a number according to user preferences.
     */
    public static function formatNumber(Request $request, float $number): string
    {
        $separators = self::getNumberSeparators($request);

        return number_format(
            $number,
            2,
            $separators['decimal'],
            $separators['thousand']
        );
    }

    /**
     * Validates the number format.
     */
    public static function validateNumberFormat(string $format): string
    {
        $decoded = json_decode($format, true);

        if (is_array($decoded) && isset($decoded['decimal']) && isset($decoded['thousand'])) {
            return $format;
        }

        return self::DEFAULTS['number_format'];
    }
}
