<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fetches, stores and applies currency exchange-rate conversions.
 *
 * Rates are retrieved twice per day (see `currency:update-rates` in
 * `routes/console.php`) from the ECB-backed Frankfurter API and persisted
 * in the `currency_rates` table, keyed by base→target pair. Stored rates
 * are expressed as: 1 unit of `base_currency` = N units of `target_currency`.
 *
 * The service degrades gracefully: when a convert is requested for a pair
 * with no stored rate (e.g. before the first scheduled fetch), it returns a
 * fallback of 1.0 instead of failing, so application rendering stays stable.
 */
final class CurrencyRateService
{
    public function __construct(
        private readonly ?CircuitBreaker $circuitBreaker = null,
        private readonly ?FeatureFlagService $featureFlags = null,
    ) {}

    /**
     * Base currency used by the provider (Frankfurter default is EUR).
     */
    public const BASE_CURRENCY = 'EUR';

    /**
     * Provider endpoint. Latest ECB reference rates relative to the base.
     */
    private const ENDPOINT = 'https://api.frankfurter.app/latest';

    /**
     * HTTP timeout in seconds.
     */
    private const TIMEOUT = 5;

    /**
     * Cache lifetime for stored rates (minutes). Rates are refreshed twice
     * per day, so a 6h TTL avoids re-reading the DB on every conversion while
     * staying well within the refresh window.
     */
    private const RATE_CACHE_TTL = 360;

    /**
     * Sentinel cached for a pair that has no stored rate. `Cache::remember()` cannot
     * store "there is no value" as null — a null read is treated as a miss again — so
     * without it every conversion of an unknown pair would hit the database.
     */
    private const NO_RATE_CACHE_VALUE = '__no_rate__';

    /**
     * Fetches the latest rates from the provider and persists each pair.
     *
     * @return int Number of rate pairs stored/updated.
     */
    public function updateRates(): int
    {
        $rates = $this->fetchRatesFromProvider();

        if ($rates === []) {
            return 0;
        }

        $now = now();
        $stored = 0;

        foreach ($rates as $target => $rate) {
            if ((float) $rate <= 0.0) {
                continue;
            }

            $target = strtoupper((string) $target);

            ExchangeRate::updateOrCreate(
                [
                    'base_currency' => self::BASE_CURRENCY,
                    'target_currency' => $target,
                ],
                [
                    'rate' => (float) $rate,
                    'fetched_at' => $now,
                ]
            );

            Cache::forget('currency_rate:'.self::BASE_CURRENCY.':'.$target);
            Cache::forget('currency_rate:'.$target.':'.self::BASE_CURRENCY);

            $stored++;
        }

        return $stored;
    }

    /**
     * Returns the stored rate for a single base→target pair, or null when
     * no stored rate is available yet.
     */
    public function storedRate(string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $cached = Cache::remember(
            "currency_rate:{$from}:{$to}",
            now()->addMinutes(self::RATE_CACHE_TTL),
            fn (): float|string => $this->queryStoredRate($from, $to) ?? self::NO_RATE_CACHE_VALUE,
        );

        return is_numeric($cached) ? (float) $cached : null;
    }

    /**
     * Queries the database for a stored base→target rate (uncached).
     */
    private function queryStoredRate(string $from, string $to): ?float
    {
        $row = ExchangeRate::where('base_currency', $from)
            ->where('target_currency', $to)
            ->first();

        if (! $row) {
            return null;
        }

        // `updateRates()` only stores positive rates, so a non-positive row can only
        // come from a manual insert. Dividing by it would turn every conversion negative.
        $rate = (float) $row->rate;

        return $rate > 0.0 ? $rate : null;
    }

    /**
     * Returns the conversion rate for a from→to pair (1 from = N to),
     * falling back to 1.0 when no stored rate is available.
     */
    public function getRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $direct = $this->storedRate($from, $to);
        if ($direct !== null) {
            return $direct;
        }

        // Resolve through the provider base: rate(BASE→TO) / rate(BASE→FROM).
        $in = $this->baseUnitRate($from);
        $out = $this->baseUnitRate($to);

        if ($in === null || $out === null || $in <= 0.0) {
            return 1.0;
        }

        return $out / $in;
    }

    /**
     * Converts an amount from a source currency into a target currency.
     *
     * @return float Converted amount (or the original amount when no rate).
     */
    public function convert(float $amount, string $from, string $to): float
    {
        return $amount * $this->getRate($from, $to);
    }

    /**
     * Returns the provider rate of N units of the currency per 1 base unit.
     */
    private function baseUnitRate(string $currency): ?float
    {
        if (strtoupper($currency) === self::BASE_CURRENCY) {
            return 1.0;
        }

        return $this->storedRate(self::BASE_CURRENCY, $currency);
    }

    /**
     * Queries the provider for the latest rates.
     *
     * @return array<string, float> target currency → rate. Empty on failure.
     */
    private function fetchRatesFromProvider(): array
    {
        $featureFlags = $this->featureFlags ?? new FeatureFlagService;

        if (! $featureFlags->enabled('external_currency_rates')) {
            return [];
        }

        try {
            $breaker = $this->circuitBreaker ?? new CircuitBreaker;

            $result = $breaker->run('frankfurter', function (): array {
                $response = Http::timeout(self::TIMEOUT)
                    ->retry(3, 100, throw: false)
                    ->acceptJson()
                    ->get(self::ENDPOINT, [
                        'from' => self::BASE_CURRENCY,
                    ]);

                if (! $response->successful()) {
                    throw new \RuntimeException('Currency provider returned an unsuccessful response.');
                }

                $payload = $response->json();

                if (! is_array($payload) || ! isset($payload['rates']) || ! is_array($payload['rates'])) {
                    throw new \RuntimeException('Currency provider returned an invalid payload.');
                }

                $rates = [];

                foreach ($payload['rates'] as $target => $rate) {
                    if (is_numeric($rate)) {
                        $rates[strtoupper((string) $target)] = (float) $rate;
                    }
                }

                return $rates;
            }, []);

            if (! is_array($result)) {
                return [];
            }

            /** @var array<string, float> $result */
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
