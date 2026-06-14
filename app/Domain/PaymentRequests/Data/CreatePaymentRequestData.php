<?php

namespace App\Domain\PaymentRequests\Data;

use Carbon\CarbonImmutable;

final readonly class CreatePaymentRequestData
{
    public function __construct(
        public string $requesterId,
        public string $currencyId,
        public string $exchangeRateSourceId,
        public string $title,
        public ?string $description,
        public string $amount,
        public string $eurExchangeRate,
        public string $amountEur,
        public CarbonImmutable $exchangeRateFetchedAt,
        public CarbonImmutable $expiresAt,
    ) {
    }
}
