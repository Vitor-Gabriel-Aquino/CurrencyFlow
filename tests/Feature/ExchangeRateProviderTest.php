<?php

namespace Tests\Feature;

use App\Domain\ExchangeRates\Contracts\ExchangeRateProvider;
use App\Domain\ExchangeRates\Exceptions\ExchangeRateProviderException;
use App\Domain\Shared\ValueObjects\CurrencyCode;
use App\Infrastructure\ExchangeRates\ExchangeRateApiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateProviderTest extends TestCase
{
    public function test_exchange_rate_provider_is_bound_to_exchange_rate_api_implementation(): void
    {
        $this->assertInstanceOf(ExchangeRateApiProvider::class, $this->app->make(ExchangeRateProvider::class));
    }

    public function test_provider_fetches_eur_to_local_currency_rate(): void
    {
        Http::fake([
            'open.er-api.com/v6/latest/EUR' => Http::response([
                'result' => 'success',
                'time_last_update_unix' => 1_718_496_000,
                'base_code' => 'EUR',
                'rates' => [
                    'EUR' => 1,
                    'BRL' => 5.8818485,
                ],
            ]),
        ]);

        $quote = $this->app->make(ExchangeRateProvider::class)
            ->getEurExchangeRate(CurrencyCode::fromString('brl'));

        $this->assertSame('EUR', $quote->baseCurrencyCode);
        $this->assertSame('BRL', $quote->localCurrencyCode);
        $this->assertSame('5.88184850', $quote->eurExchangeRate);
        $this->assertSame('ExchangeRate-API', $quote->source);
        $this->assertSame('2024-06-16 00:00:00', $quote->fetchedAt->format('Y-m-d H:i:s'));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://open.er-api.com/v6/latest/EUR');
    }

    public function test_provider_reports_http_failures_as_unavailable(): void
    {
        Http::fake([
            'open.er-api.com/v6/latest/EUR' => Http::response([], 429),
        ]);

        $this->expectException(ExchangeRateProviderException::class);
        $this->expectExceptionMessage('Exchange rate provider is unavailable.');

        $this->app->make(ExchangeRateProvider::class)
            ->getEurExchangeRate(CurrencyCode::fromString('BRL'));
    }

    public function test_provider_reports_unexpected_payloads(): void
    {
        Http::fake([
            'open.er-api.com/v6/latest/EUR' => Http::response([
                'result' => 'error',
                'error-type' => 'malformed-request',
            ]),
        ]);

        $this->expectException(ExchangeRateProviderException::class);
        $this->expectExceptionMessage('Exchange rate provider returned an unexpected response.');

        $this->app->make(ExchangeRateProvider::class)
            ->getEurExchangeRate(CurrencyCode::fromString('BRL'));
    }

    public function test_provider_reports_unexpected_payload_when_base_currency_is_not_eur(): void
    {
        Http::fake([
            'open.er-api.com/v6/latest/EUR' => Http::response([
                'result' => 'success',
                'time_last_update_unix' => 1_718_496_000,
                'base_code' => 'USD',
                'rates' => [
                    'BRL' => 5.8818485,
                ],
            ]),
        ]);

        $this->expectException(ExchangeRateProviderException::class);
        $this->expectExceptionMessage('Exchange rate provider returned an unexpected response.');

        $this->app->make(ExchangeRateProvider::class)
            ->getEurExchangeRate(CurrencyCode::fromString('BRL'));
    }

    public function test_provider_reports_unexpected_payload_when_rates_are_missing(): void
    {
        Http::fake([
            'open.er-api.com/v6/latest/EUR' => Http::response([
                'result' => 'success',
                'time_last_update_unix' => 1_718_496_000,
                'base_code' => 'EUR',
            ]),
        ]);

        $this->expectException(ExchangeRateProviderException::class);
        $this->expectExceptionMessage('Exchange rate provider returned an unexpected response.');

        $this->app->make(ExchangeRateProvider::class)
            ->getEurExchangeRate(CurrencyCode::fromString('BRL'));
    }

    public function test_provider_reports_unexpected_payload_when_timestamp_is_missing(): void
    {
        Http::fake([
            'open.er-api.com/v6/latest/EUR' => Http::response([
                'result' => 'success',
                'base_code' => 'EUR',
                'rates' => [
                    'BRL' => 5.8818485,
                ],
            ]),
        ]);

        $this->expectException(ExchangeRateProviderException::class);
        $this->expectExceptionMessage('Exchange rate provider returned an unexpected response.');

        $this->app->make(ExchangeRateProvider::class)
            ->getEurExchangeRate(CurrencyCode::fromString('BRL'));
    }

    public function test_provider_reports_missing_local_currency_rate(): void
    {
        Http::fake([
            'open.er-api.com/v6/latest/EUR' => Http::response([
                'result' => 'success',
                'time_last_update_unix' => 1_718_496_000,
                'base_code' => 'EUR',
                'rates' => [
                    'EUR' => 1,
                ],
            ]),
        ]);

        $this->expectException(ExchangeRateProviderException::class);
        $this->expectExceptionMessage('Exchange rate for BRL is not available.');

        $this->app->make(ExchangeRateProvider::class)
            ->getEurExchangeRate(CurrencyCode::fromString('BRL'));
    }
}
