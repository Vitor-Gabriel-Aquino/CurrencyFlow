<?php

namespace App\Domain\ExchangeRates\Contracts;

use App\Domain\ExchangeRates\Data\ExchangeRateQuote;
use App\Domain\Shared\ValueObjects\CurrencyCode;

interface ExchangeRateProvider
{
    public function getEurExchangeRate(CurrencyCode $localCurrencyCode): ExchangeRateQuote;
}
