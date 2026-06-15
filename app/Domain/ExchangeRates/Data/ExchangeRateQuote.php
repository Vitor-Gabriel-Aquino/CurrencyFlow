<?php

namespace App\Domain\ExchangeRates\Data;

use Carbon\CarbonImmutable;

final readonly class ExchangeRateQuote
{
    public function __construct(
        public string $baseCurrencyCode,
        public string $localCurrencyCode,
        public string $eurExchangeRate,
        public string $source,
        public CarbonImmutable $fetchedAt,
    ) {
    }
}
