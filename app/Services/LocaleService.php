<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Central locale (language) service.
 *
 * Centralizes access to `config('locales')` — the single source of truth — and
 * provides user preference resolution from the request.
 *
 * @phpstan-type LocaleMeta array{
 *     name: string,
 *     native: string,
 *     flag: string,
 *     rtl: bool,
 *     default_locale: string,
 *     continent: string,
 *     unit_system: string,
 *     number_format: array{decimal: string, thousand: string},
 *     currencies: list<string>
 * }
 */
final class LocaleService
{
    /**
     * Fallback currency symbols by ISO 4217 code, used when `intl` is absent
     * or when a plain symbol is required outside a full currency formatting.
     */
    private const CURRENCY_SYMBOLS = [
        'EUR' => '€',
        'GBP' => '£',
        'USD' => '$',
        'BRL' => 'R$',
        'JPY' => '¥',
        'CNY' => '¥',
        'PLN' => 'zł',
        'ARS' => '$',
        'CAD' => 'CA$',
        'MXN' => 'MX$',
        'INR' => '₹',
        'AED' => 'AED',
        'KRW' => '₩',
        'ALL' => 'L',
        'AZN' => '₼',
        'BAM' => 'KM',
        'BGN' => 'лв',
        'BYN' => 'Br',
        'CHF' => 'Fr',
        'CZK' => 'Kč',
        'DKK' => 'kr',
        'GEL' => '₾',
        'HUF' => 'Ft',
        'ISK' => 'kr',
        'MDL' => 'L',
        'MKD' => 'ден',
        'RON' => 'lei',
        'RSD' => 'дин',
        'RUB' => '₽',
        'SEK' => 'kr',
        'TRY' => '₺',
        'UAH' => '₴',
        'AMD' => '֏',
        'THB' => '฿',
        'VND' => '₫',
        'IDR' => 'Rp',
        'HKD' => 'HK$',
        'TWD' => 'NT$',
        'SGD' => 'S$',
        'CLP' => 'CLP$',
        'COP' => 'COL$',
        'SAR' => 'SAR',
        'EGP' => 'E£',
        'NOK' => 'kr',
        'AUD' => 'A$',
    ];

    /**
     * Returns the plain symbol for an ISO 4217 currency code, or the code
     * itself when no symbol is known.
     */
    public static function currencySymbol(string $currency): string
    {
        return self::CURRENCY_SYMBOLS[strtoupper($currency)] ?? strtoupper($currency);
    }

    /**
     * Lista de idiomas suportados.
     *
     * @return array<string, LocaleMeta>
     */
    public static function grouped(): array
    {
        $locales = config('locales');
        $languages = is_array($locales) ? ($locales['languages'] ?? null) : null;

        if (! is_array($languages)) {
            return [];
        }

        $grouped = [];

        foreach ($languages as $code => $meta) {
            if (is_string($code) && self::isLocaleMeta($meta)) {
                $grouped[$code] = $meta;
            }
        }

        return $grouped;
    }

    /**
     * @phpstan-assert-if-true LocaleMeta $meta
     */
    private static function isLocaleMeta(mixed $meta): bool
    {
        return is_array($meta)
            && isset($meta['name'], $meta['native'], $meta['flag'], $meta['rtl'], $meta['default_locale'], $meta['continent'], $meta['unit_system'], $meta['number_format'], $meta['currencies'])
            && is_string($meta['name'])
            && is_string($meta['native'])
            && is_string($meta['flag'])
            && is_bool($meta['rtl'])
            && is_string($meta['default_locale'])
            && is_string($meta['continent'])
            && is_string($meta['unit_system'])
            && is_array($meta['number_format'])
            && is_array($meta['currencies']);
    }

    /**
     * List of supported languages grouped by continent.
     *
     * @return array<string, list<LocaleMeta>>
     */
    public static function groupedByContinent(): array
    {
        $grouped = [];

        foreach (self::all() as $code => $meta) {
            $continent = $meta['continent'];
            $grouped[$continent][] = [
                'code' => $code,
                ...$meta,
            ];
        }

        return $grouped;
    }

    /**
     * List of supported currencies grouped by continent.
     *
     * @return array<string, list<string>>
     */
    public static function currenciesByContinent(): array
    {
        $currencyContinents = [
            'EUR' => 'europe',
            'USD' => 'north_america',
            'GBP' => 'europe',
            'BRL' => 'south_america',
            'JPY' => 'asia',
            'CNY' => 'asia',
            'PLN' => 'europe',
            'ARS' => 'south_america',
            'CAD' => 'north_america',
            'MXN' => 'north_america',
            'INR' => 'asia',
            'AED' => 'asia',
            'KRW' => 'asia',
            'ALL' => 'europe',
            'AZN' => 'asia',
            'BAM' => 'europe',
            'BGN' => 'europe',
            'BYN' => 'europe',
            'CHF' => 'europe',
            'CZK' => 'europe',
            'DKK' => 'europe',
            'GEL' => 'asia',
            'HUF' => 'europe',
            'ISK' => 'europe',
            'MDL' => 'europe',
            'MKD' => 'europe',
            'RON' => 'europe',
            'RSD' => 'europe',
            'RUB' => 'europe',
            'SEK' => 'europe',
            'TRY' => 'asia',
            'UAH' => 'europe',
            'AMD' => 'asia',
            'THB' => 'asia',
            'VND' => 'asia',
            'IDR' => 'asia',
            'HKD' => 'asia',
            'TWD' => 'asia',
            'SGD' => 'asia',
            'CLP' => 'south_america',
            'COP' => 'south_america',
            'SAR' => 'asia',
            'EGP' => 'africa',
            'NOK' => 'europe',
            'AUD' => 'oceania',
        ];

        $grouped = [];

        foreach (self::supportedCurrencies() as $currency) {
            $continent = $currencyContinents[$currency] ?? 'other';
            $grouped[$continent][] = $currency;
        }

        return $grouped;
    }

    /**
     * List of supported currencies.
     *
     * The currency list is independent of configured languages, so it is
     * delegated to `PreferencesService::supportedCurrencies()`.
     *
     * @return list<string>
     */
    public static function supportedCurrencies(): array
    {
        return PreferencesService::supportedCurrencies();
    }

    /**
     * Full name of the ISO 4217 currency.
     */
    public static function currencyName(string $currency): string
    {
        $names = [
            'EUR' => 'Euro',
            'USD' => 'Dólar Americano',
            'GBP' => 'Libra Esterlina',
            'BRL' => 'Real Brasileiro',
            'JPY' => 'Iene Japonês',
            'CNY' => 'Yuan Chinês',
            'PLN' => 'Złoty Polaco',
            'ARS' => 'Peso Argentino',
            'CAD' => 'Dólar Canadiano',
            'MXN' => 'Peso Mexicano',
            'INR' => 'Rupia Indiana',
            'AED' => 'Dirham dos EAU',
            'KRW' => 'Won Sul-Coreano',
            'ALL' => 'Lek Albanês',
            'AZN' => 'Manat Azerbaijano',
            'BAM' => 'Marco Convertível',
            'BGN' => 'Lev Búlgaro',
            'BYN' => 'Rublo Bielorrusso',
            'CHF' => 'Franco Suíço',
            'CZK' => 'Coroa Checa',
            'DKK' => 'Coroa Dinamarquesa',
            'GEL' => 'Lari Georgiano',
            'HUF' => 'Florim Húngaro',
            'ISK' => 'Coroa Islandesa',
            'MDL' => 'Leu Moldavo',
            'MKD' => 'Denar Macedónio',
            'RON' => 'Leu Romeno',
            'RSD' => 'Dinar Sérvio',
            'RUB' => 'Rublo Russo',
            'SEK' => 'Coroa Sueca',
            'TRY' => 'Lira Turca',
            'UAH' => 'Hryvnia Ucraniana',
            'AMD' => 'Dram Arménio',
            'THB' => 'Baht Tailandês',
            'VND' => 'Dong Vietnamita',
            'IDR' => 'Rupia Indonésia',
            'HKD' => 'Dólar de Hong Kong',
            'TWD' => 'Dólar Taiwanês',
            'SGD' => 'Dólar de Singapura',
            'CLP' => 'Peso Chileno',
            'COP' => 'Peso Colombiano',
            'SAR' => 'Rial Saudita',
            'EGP' => 'Libra Egípcia',
            'NOK' => 'Coroa Norueguesa',
            'AUD' => 'Dólar Australiano',
        ];

        return $names[strtoupper($currency)] ?? $currency;
    }

    /**
     * Flat list of all languages (code => metadata).
     *
     * @return array<string, LocaleMeta>
     */
    public static function all(): array
    {
        $flattened = [];

        foreach (self::grouped() as $code => $meta) {
            $flattened[$code] = $meta;
        }

        self::sortByName($flattened);

        return $flattened;
    }

    /**
     * Sorts alphabetically by name (with accent support if intl is available).
     *
     * @param  array<string, LocaleMeta>  $locales
     */
    private static function sortByName(array &$locales): void
    {
        if (class_exists(\Collator::class)) {
            $collator = new \Collator('pt_PT');

            uasort($locales, static fn (array $a, array $b): int => (int) $collator->compare(
                $a['name'],
                $b['name'],
            ));

            return;
        }

        uasort($locales, static fn (array $a, array $b): int => strcasecmp(
            $a['name'],
            $b['name'],
        ));
    }

    /**
     * Codes of all supported languages.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /**
     * Resolves a language code to its default formatting locale.
     *
     * @return array{code: string, locale: string}
     */
    public static function resolveLocale(?string $locale = null): array
    {
        $targetLocale = $locale ?? app()->getLocale();

        if (str_contains($targetLocale, '-')) {
            return ['code' => $targetLocale, 'locale' => $targetLocale];
        }

        $meta = self::meta($targetLocale);

        if ($meta !== null) {
            return ['code' => $targetLocale, 'locale' => $meta['default_locale']];
        }

        return ['code' => $targetLocale, 'locale' => $targetLocale];
    }

    /**
     * System default language.
     */
    public static function default(): string
    {
        $defaultLang = config('locales.default', 'pt');
        $defaultLang = is_string($defaultLang) && $defaultLang !== '' ? $defaultLang : 'pt';
        $meta = self::meta($defaultLang);

        if ($meta !== null) {
            return $meta['default_locale'];
        }

        return 'pt-PT';
    }

    /**
     * Checks whether a language code is supported.
     */
    public static function isSupported(string $locale): bool
    {
        if (array_key_exists($locale, self::all())) {
            return true;
        }

        $base = strtolower(explode('-', $locale)[0]);
        foreach (self::all() as $code => $meta) {
            if (strtolower(explode('-', $code)[0]) === $base) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolves the default_locale for a base language code.
     *
     * @return array{code: string, default_locale: string}
     */
    public static function resolveDefaultLocale(string $locale): array
    {
        $meta = self::meta($locale);

        if ($meta !== null) {
            return ['code' => $locale, 'default_locale' => $meta['default_locale']];
        }

        $base = strtolower(explode('-', $locale)[0]);
        foreach (self::all() as $code => $meta) {
            if (strtolower(explode('-', $code)[0]) === $base) {
                return ['code' => $locale, 'default_locale' => $meta['default_locale']];
            }
        }

        return ['code' => $locale, 'default_locale' => self::default()];
    }

    /**
     * Language metadata (null if not supported).
     *
     * @return LocaleMeta|null
     */
    public static function meta(string $locale): ?array
    {
        return self::all()[$locale] ?? null;
    }

    /**
     * Tax identifier label used in the active language's region.
     */
    public static function taxIdentifierLabel(?string $locale = null): string
    {
        $code = $locale ?? app()->getLocale();

        return match ($code) {
            'pt-PT' => 'NIF',
            'pt-BR' => 'CNPJ',
            'en-GB' => 'UTR',
            'en-US' => 'EIN',
            'es-ES' => 'NIF',
            'fr-FR' => 'SIRET',
            'de-DE' => 'Steuernummer',
            'it-IT' => 'Partita IVA',
            'nl-NL' => 'BTW-nummer',
            'pl-PL' => 'NIP',
            'zh-CN' => 'USCC',
            'ja-JP' => 'Corporate Number',
            'ko-KR' => 'BRN',
            'hi-IN' => 'GSTIN',
            'ar-AE' => 'TRN',
            default => 'Tax ID',
        };
    }

    /**
     * Local designation for consumption tax equivalent to VAT.
     */
    public static function indirectTaxLabel(?string $locale = null): string
    {
        $code = $locale ?? app()->getLocale();

        return match ($code) {
            'pt-PT', 'pt-BR' => 'IVA',
            'en-GB', 'en-US', 'ar-AE' => 'VAT',
            'es-ES' => 'IVA',
            'fr-FR' => 'TVA',
            'de-DE' => 'MwSt.',
            'it-IT' => 'IVA',
            'nl-NL' => 'btw',
            'pl-PL' => 'VAT',
            'zh-CN' => '增值税',
            'ja-JP' => '消費税',
            'ko-KR' => '부가가치세',
            'hi-IN' => 'GST',
            default => 'VAT',
        };
    }

    /**
     * Returns the Unicode flag emoji for the given ISO-3166 alpha-2 country code.
     */
    public static function flagEmoji(?string $countryCode): string
    {
        $code = strtoupper(trim((string) $countryCode));

        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return '🏳️';
        }

        return implode('', array_map(
            static fn (string $letter): string => mb_chr(127397 + ord($letter)),
            str_split($code),
        ));
    }

    /**
     * Indicates whether the language is right-to-left.
     */
    public static function isRtl(string $locale): bool
    {
        $meta = self::meta($locale);

        return (bool) ($meta['rtl'] ?? false);
    }

    /**
     * ISO 4217 currency for the indicated (or current) language.
     */
    public static function currency(?string $locale = null): string
    {
        $targetLocale = $locale ?? app()->getLocale();
        $meta = self::meta($targetLocale);

        return $meta['currencies'][0] ?? 'EUR';
    }

    /**
     * Unit system for the indicated (or current) language.
     */
    public static function unitSystem(?string $locale = null): string
    {
        $targetLocale = $locale ?? app()->getLocale();
        $meta = self::meta($targetLocale);

        return $meta['unit_system'] ?? 'metric';
    }

    /**
     * Formats a number according to the indicated (or current) language.
     */
    public static function formatNumber(int|float $value, int $decimals = 0, ?string $locale = null): string
    {
        $resolved = self::resolveLocale($locale);
        $targetLocale = $resolved['locale'];

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($targetLocale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

            $result = $formatter->format($value);
            if ($result !== false) {
                return $result;
            }
        }

        $meta = self::meta($resolved['code']);
        $numberFormat = $meta['number_format'] ?? ['decimal' => '.', 'thousand' => ','];
        $decPoint = $numberFormat['decimal'];
        $thousandsSep = $numberFormat['thousand'];

        return number_format((float) $value, $decimals, $decPoint, $thousandsSep);
    }

    /**
     * Formats a monetary amount in the indicated (or current) language's currency.
     */
    public static function formatCurrency(int|float $value, ?string $currency = null, ?string $locale = null): string
    {
        $resolved = self::resolveLocale($locale);
        $targetLocale = $resolved['locale'];
        $currencyCode = $currency ?? self::currency($targetLocale);

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($targetLocale, \NumberFormatter::CURRENCY);
            $result = $formatter->formatCurrency($value, $currencyCode);
            if ($result !== false) {
                return $result;
            }
        }

        $formattedNum = self::formatNumber($value, 2, $targetLocale);
        $symbol = self::currencySymbol($currencyCode);

        if (in_array($targetLocale, ['en-US', 'en-GB'], true)) {
            return "{$symbol}{$formattedNum}";
        }

        return "{$formattedNum} {$symbol}";
    }

    /**
     * Formats a percentage — the value is the actual percentage (55 = "55%").
     */
    public static function formatPercent(int|float $value, int $decimals = 1, ?string $locale = null): string
    {
        $resolved = self::resolveLocale($locale);
        $targetLocale = $resolved['locale'];

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($targetLocale, \NumberFormatter::PERCENT);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

            $result = $formatter->format($value / 100);
            if ($result !== false) {
                return $result;
            }
        }

        $formattedNum = self::formatNumber($value, $decimals, $targetLocale);

        return "{$formattedNum}%";
    }

    /**
     * Formats a date (short) according to the indicated (or current) language.
     */
    public static function formatDate(mixed $value, ?string $locale = null): string
    {
        $date = self::asDateTime($value);

        if ($date === null) {
            return '';
        }

        $resolved = self::resolveLocale($locale);
        $targetLocale = $resolved['locale'];

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = \IntlDateFormatter::create(
                $targetLocale,
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::NONE,
            );

            if ($formatter) {
                $result = $formatter->format($date);
                if ($result !== false) {
                    return $result;
                }
            }
        }

        return $date->format('d/m/Y');
    }

    /**
     * Formats date and time according to the indicated (or current) language.
     */
    public static function formatDateTime(mixed $value, ?string $locale = null): string
    {
        $date = self::asDateTime($value);

        if ($date === null) {
            return '';
        }

        $resolved = self::resolveLocale($locale);
        $targetLocale = $resolved['locale'];

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = \IntlDateFormatter::create(
                $targetLocale,
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::SHORT,
            );

            if ($formatter) {
                $result = $formatter->format($date);
                if ($result !== false) {
                    return $result;
                }
            }
        }

        return $date->format('d/m/Y H:i');
    }

    /**
     * Converts a unit of measurement based on the language's unit system.
     *
     * @return array{value: float, unit: string, formatted: string}
     */
    public static function convertUnit(float|int $value, string $type, string $fromUnit = '', ?string $locale = null): array
    {
        $numValue = (float) $value;
        $resolved = self::resolveLocale($locale);
        $targetLocale = $resolved['locale'];
        $unitSys = self::unitSystem($targetLocale);

        switch (strtolower($type)) {
            case 'temperature':
                $isImperial = ($unitSys === 'imperial_us');
                if ($isImperial && in_array(strtolower($fromUnit), ['c', 'celsius', '°c', ''], true)) {
                    $converted = ($numValue * 9 / 5) + 32;
                    $unit = '°F';
                } elseif (! $isImperial && in_array(strtolower($fromUnit), ['f', 'fahrenheit', '°f'], true)) {
                    $converted = ($numValue - 32) * 5 / 9;
                    $unit = '°C';
                } else {
                    $converted = $numValue;
                    $unit = in_array(strtolower($fromUnit), ['f', 'fahrenheit', '°f'], true) ? '°F' : '°C';
                }
                break;

            case 'distance':
            case 'length':
                $isImperial = in_array($unitSys, ['imperial_uk', 'imperial_us'], true);
                $from = strtolower($fromUnit);

                if ($isImperial && in_array($from, ['km', 'kilometres', 'quilómetros'], true)) {
                    $converted = $numValue * 0.621371;
                    $unit = 'mi';
                } elseif ($isImperial && in_array($from, ['m', 'meters', 'metros', ''], true)) {
                    $converted = $numValue * 3.28084;
                    $unit = 'ft';
                } elseif (! $isImperial && in_array($from, ['mi', 'miles', 'milhas'], true)) {
                    $converted = $numValue / 0.621371;
                    $unit = 'km';
                } elseif (! $isImperial && in_array($from, ['ft', 'feet', 'pés'], true)) {
                    $converted = $numValue / 0.621371;
                    $unit = 'km';
                } else {
                    $converted = $numValue;
                    $unit = $fromUnit !== '' ? $fromUnit : ($isImperial ? 'ft' : 'm');
                }
                break;

            case 'weight':
                $isImperial = in_array($unitSys, ['imperial_uk', 'imperial_us'], true);
                $from = strtolower($fromUnit);

                if ($isImperial && in_array($from, ['kg', 'kilos', 'quilogramas', ''], true)) {
                    $converted = $numValue * 2.20462;
                    $unit = 'lbs';
                } elseif (! $isImperial && in_array($from, ['lbs', 'lb', 'libras'], true)) {
                    $converted = $numValue / 2.20462;
                    $unit = 'kg';
                } else {
                    $converted = $numValue;
                    $unit = $fromUnit !== '' ? $fromUnit : ($isImperial ? 'lbs' : 'kg');
                }
                break;

            case 'volume':
                $isUsImperial = ($unitSys === 'imperial_us');
                $from = strtolower($fromUnit);

                if ($isUsImperial && in_array($from, ['l', 'liter', 'litro', 'litros', ''], true)) {
                    $converted = $numValue * 0.264172;
                    $unit = 'gal';
                } elseif (! $isUsImperial && in_array($from, ['gal', 'gallon', 'galão', 'galões'], true)) {
                    $converted = $numValue / 0.264172;
                    $unit = 'L';
                } else {
                    $converted = $numValue;
                    $unit = $fromUnit !== '' ? $fromUnit : ($isUsImperial ? 'gal' : 'L');
                }
                break;

            default:
                $converted = $numValue;
                $unit = $fromUnit;
                break;
        }

        $formattedNum = self::formatNumber($converted, 2, $targetLocale);

        return [
            'value' => round($converted, 2),
            'unit' => $unit,
            'formatted' => "{$formattedNum} {$unit}",
        ];
    }

    /**
     * Normalizes a value to \DateTime (null if invalid).
     */
    private static function asDateTime(mixed $value): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Normalizes a locale to a supported code; returns the default if invalid.
     */
    public static function sanitize(string $locale): string
    {
        if (self::isSupported($locale)) {
            $meta = self::meta($locale);
            if ($meta !== null) {
                return $meta['default_locale'];
            }

            $base = strtolower(explode('-', $locale)[0]);
            foreach (self::all() as $code => $meta) {
                if (strtolower(explode('-', $code)[0]) === $base) {
                    return $meta['default_locale'];
                }
            }

            return $locale;
        }

        $base = strtolower(explode('-', $locale)[0]);
        foreach (self::all() as $code => $meta) {
            if (strtolower(explode('-', $code)[0]) === $base) {
                return $meta['default_locale'];
            }
        }

        return self::default();
    }

    /**
     * Resolves the language from the request's preference.
     */
    public static function resolveFromRequest(Request $request): string
    {
        $fromSession = self::fromSession($request);
        $fromCookie = self::fromCookie($request);
        $fromBrowser = self::fromBrowser($request);

        return self::sanitize($fromSession ?? $fromCookie ?? $fromBrowser ?? self::default());
    }

    /**
     * Resolves the language from the browser's `Accept-Language` header.
     */
    public static function fromBrowser(Request $request): ?string
    {
        $languages = $request->getLanguages();

        if (empty($languages)) {
            return null;
        }

        $byCode = self::all();

        foreach ($languages as $language) {
            if (isset($byCode[$language])) {
                return $language;
            }
        }

        foreach ($languages as $language) {
            $base = strtolower(explode('-', $language)[0]);

            foreach ($byCode as $code => $meta) {
                if (strtolower(explode('-', $code)[0]) === $base) {
                    return $code;
                }
            }
        }

        return null;
    }

    private static function fromSession(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $locale = $request->session()->get('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    private static function fromCookie(Request $request): ?string
    {
        $locale = $request->cookie('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    /**
     * Gets the active currency for the user (from preferences).
     */
    public static function userCurrency(?Request $request = null): string
    {
        if ($request) {
            return PreferencesService::getCurrency($request);
        }

        $user = Auth::user();
        if ($user) {
            $prefs = PreferencesService::forUser($user);

            return $prefs['currency'];
        }

        return 'EUR';
    }

    /**
     * Gets the active date format for the user (from preferences).
     */
    public static function userDateFormat(?Request $request = null): string
    {
        if ($request) {
            return PreferencesService::getDateFormat($request);
        }

        $user = Auth::user();
        if ($user) {
            $prefs = PreferencesService::forUser($user);

            return $prefs['date_format'];
        }

        return 'd/m/Y';
    }

    /**
     * Formats a monetary value using the user's currency.
     */
    public static function formatMoney(int|float $value, ?Request $request = null, ?string $currency = null): string
    {
        $userCurrency = $currency ?? self::userCurrency($request);

        return self::formatCurrency($value, $userCurrency);
    }

    /**
     * Formats a date using the user's format.
     */
    public static function formatUserDate(mixed $value, ?Request $request = null, ?string $format = null): string
    {
        $userFormat = $format ?? self::userDateFormat($request);

        if ($value instanceof \DateTimeInterface) {
            return $value->format($userFormat);
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return '';
        }

        try {
            $date = new \DateTimeImmutable((string) $value);

            return $date->format($userFormat);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
