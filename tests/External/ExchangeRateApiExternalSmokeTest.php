<?php

namespace Tests\External;

use App\Domain\ExchangeRates\Contracts\ExchangeRateProvider;
use App\Domain\ExchangeRates\Data\ExchangeRateQuote;
use App\Domain\Shared\ValueObjects\CurrencyCode;
use Tests\TestCase;

class ExchangeRateApiExternalSmokeTest extends TestCase
{
    public function test_exchange_rate_api_open_access_contract_is_still_compatible(): void
    {
        if (! filter_var(env('RUN_EXTERNAL_API_TESTS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('External API tests are disabled. Set RUN_EXTERNAL_API_TESTS=true to run them.');
        }

        $quote = $this->app->make(ExchangeRateProvider::class)
            ->getEurExchangeRate(CurrencyCode::fromString('BRL'));

        $this->assertInstanceOf(ExchangeRateQuote::class, $quote);
        $this->assertSame('EUR', $quote->baseCurrencyCode);
        $this->assertSame('BRL', $quote->localCurrencyCode);
        $this->assertIsNumeric($quote->eurExchangeRate);
        $this->assertGreaterThan(0, (float) $quote->eurExchangeRate);
        $this->assertSame('ExchangeRate-API', $quote->source);
        $this->assertTrue($quote->fetchedAt->isPast() || $quote->fetchedAt->isNow());
    }
}
