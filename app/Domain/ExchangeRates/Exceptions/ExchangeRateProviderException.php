<?php

namespace App\Domain\ExchangeRates\Exceptions;

use RuntimeException;

class ExchangeRateProviderException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self('Exchange rate provider is unavailable.');
    }

    public static function unexpectedResponse(): self
    {
        return new self('Exchange rate provider returned an unexpected response.');
    }

    public static function missingRate(string $currencyCode): self
    {
        return new self("Exchange rate for {$currencyCode} is not available.");
    }
}
