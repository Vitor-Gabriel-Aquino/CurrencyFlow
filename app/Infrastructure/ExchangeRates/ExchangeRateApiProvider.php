<?php

namespace App\Infrastructure\ExchangeRates;

use App\Domain\ExchangeRates\Contracts\ExchangeRateProvider;
use App\Domain\ExchangeRates\Data\ExchangeRateQuote;
use App\Domain\ExchangeRates\Exceptions\ExchangeRateProviderException;
use App\Domain\Shared\ValueObjects\CurrencyCode;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ExchangeRateApiProvider implements ExchangeRateProvider
{
    private const BASE_CURRENCY_CODE = 'EUR';

    public function getEurExchangeRate(CurrencyCode $localCurrencyCode): ExchangeRateQuote
    {
        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.exchange_rate_api.timeout'))
                ->get($this->url(self::BASE_CURRENCY_CODE));
        } catch (ConnectionException) {
            throw ExchangeRateProviderException::unavailable();
        }

        if (! $response->successful()) {
            throw ExchangeRateProviderException::unavailable();
        }

        $payload = $response->json();

        if (! is_array($payload)
            || ($payload['result'] ?? null) !== 'success'
            || ($payload['base_code'] ?? null) !== self::BASE_CURRENCY_CODE
            || ! isset($payload['rates'])
            || ! is_array($payload['rates'])
            || ! isset($payload['time_last_update_unix'])
            || ! is_numeric($payload['time_last_update_unix'])) {
            throw ExchangeRateProviderException::unexpectedResponse();
        }

        $rate = $payload['rates'][$localCurrencyCode->value] ?? null;

        if (! is_numeric($rate)) {
            throw ExchangeRateProviderException::missingRate($localCurrencyCode->value);
        }

        $fetchedAt = CarbonImmutable::createFromTimestampUTC((int) $payload['time_last_update_unix']);

        return new ExchangeRateQuote(
            baseCurrencyCode: self::BASE_CURRENCY_CODE,
            localCurrencyCode: $localCurrencyCode->value,
            eurExchangeRate: $this->normalizeRate($rate),
            source: (string) config('services.exchange_rate_api.source'),
            fetchedAt: $fetchedAt,
        );
    }

    private function url(string $baseCurrencyCode): string
    {
        return rtrim((string) config('services.exchange_rate_api.url'), '/').'/'.$baseCurrencyCode;
    }

    private function normalizeRate(int|float|string $rate): string
    {
        $rate = is_string($rate) ? trim($rate) : (string) $rate;

        if (str_contains(strtolower($rate), 'e')) {
            $rate = sprintf('%.14F', (float) $rate);
        }

        return bcadd($rate, '0', 8);
    }
}
