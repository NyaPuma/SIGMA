<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ExchangeRate;
use App\Services\CircuitBreaker;
use App\Services\CurrencyRateService;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CurrencyRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private CurrencyRateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['features.flags.external_currency_rates' => true]);

        $this->service = new CurrencyRateService(new CircuitBreaker, new FeatureFlagService);
    }

    private function store(string $base, string $target, float $rate): ExchangeRate
    {
        return ExchangeRate::create([
            'base_currency' => $base,
            'target_currency' => $target,
            'rate' => $rate,
            'fetched_at' => now(),
        ]);
    }

    /**
     * @return list<array{query: string, bindings: array<mixed>, time: float}>
     */
    private function measuredQueries(callable $operation): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $operation();
        DB::disableQueryLog();

        return array_values(array_filter(
            DB::getQueryLog(),
            fn (array $log): bool => str_contains($log['query'], 'currency_rates')
        ));
    }

    #[Test]
    public function it_reads_a_stored_pair_once_and_serves_the_second_call_from_cache(): void
    {
        $this->store('EUR', 'USD', 1.0834);

        $queries = $this->measuredQueries(function (): void {
            $this->assertEqualsWithDelta(1.0834, $this->service->storedRate('eur', 'usd'), 1e-6);
            $this->assertEqualsWithDelta(1.0834, $this->service->storedRate('EUR', 'USD'), 1e-6);
        });

        $this->assertCount(1, $queries);
    }

    #[Test]
    public function it_caches_a_pair_it_has_never_stored_instead_of_requerying_it(): void
    {
        $queries = $this->measuredQueries(function (): void {
            $this->assertNull($this->service->storedRate('GBP', 'JPY'));
            $this->assertNull($this->service->storedRate('GBP', 'JPY'));
            $this->assertNull($this->service->storedRate('GBP', 'JPY'));
        });

        $this->assertCount(1, $queries, 'the "no rate" answer must be cached like a rate is');
    }

    #[Test]
    public function it_reports_a_non_positive_stored_rate_as_missing(): void
    {
        $this->store('EUR', 'LYD', 0.0);

        $this->assertNull($this->service->storedRate('EUR', 'LYD'));
        $this->assertSame(1.0, $this->service->getRate('EUR', 'LYD'));
    }

    #[Test]
    public function it_returns_the_rate_of_one_unit_of_a_currency_against_itself(): void
    {
        $this->assertSame(1.0, $this->service->storedRate('USD', 'USD'));
        $this->assertSame(1.0, $this->service->getRate('USD', 'USD'));
    }

    #[Test]
    public function it_resolves_an_unstored_pair_through_the_provider_base(): void
    {
        $this->store('EUR', 'GBP', 0.85);
        $this->store('EUR', 'USD', 1.10);

        $this->assertEqualsWithDelta(1.10 / 0.85, $this->service->getRate('GBP', 'USD'), 1e-6);
        $this->assertEqualsWithDelta(100.0 * (1.10 / 0.85), $this->service->convert(100.0, 'GBP', 'USD'), 1e-6);
    }

    #[Test]
    public function it_falls_back_to_one_when_a_leg_of_the_conversion_is_unknown(): void
    {
        $this->store('EUR', 'USD', 1.10);

        $this->assertSame(1.0, $this->service->getRate('USD', 'JPY'));
        $this->assertSame(250.0, $this->service->convert(250.0, 'USD', 'JPY'));
    }

    #[Test]
    public function a_new_fetch_invalidates_both_directions_of_a_pair(): void
    {
        $this->store('EUR', 'USD', 1.00);
        $this->store('USD', 'EUR', 0.90);

        // Warm both cache directions with the pre-fetch answers.
        $this->assertEqualsWithDelta(1.00, $this->service->storedRate('EUR', 'USD'), 1e-6);
        $this->assertEqualsWithDelta(0.90, $this->service->storedRate('USD', 'EUR'), 1e-6);

        Http::fake([
            'api.frankfurter.app/latest*' => Http::response(['base' => 'EUR', 'rates' => ['USD' => 1.20]]),
        ]);

        $this->assertSame(1, $this->service->updateRates());

        $queries = $this->measuredQueries(function (): void {
            $this->assertEqualsWithDelta(1.20, $this->service->storedRate('EUR', 'USD'), 1e-6);
            $this->assertEqualsWithDelta(0.90, $this->service->storedRate('USD', 'EUR'), 1e-6);
        });

        $this->assertCount(2, $queries, 'both cache directions must be re-read after a fetch');
    }

    #[Test]
    public function it_stores_only_positive_rates_from_the_provider(): void
    {
        Http::fake([
            'api.frankfurter.app/latest*' => Http::response([
                'base' => 'EUR',
                'rates' => ['USD' => 1.20, 'GBP' => 0, 'JPY' => 'not-a-number'],
            ]),
        ]);

        $this->assertSame(1, $this->service->updateRates());

        $this->assertSame(1, ExchangeRate::count());
        $this->assertSame('USD', ExchangeRate::first()->target_currency);
    }

    #[Test]
    public function it_fetches_nothing_when_the_external_rates_flag_is_off(): void
    {
        config(['features.flags.external_currency_rates' => false]);
        Http::fake();

        $this->assertSame(0, $this->service->updateRates());

        Http::assertNothingSent();
        $this->assertSame(0, ExchangeRate::count());
    }
}
