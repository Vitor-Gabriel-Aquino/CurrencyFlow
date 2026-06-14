<?php

namespace App\Domain\PaymentRequests\Data;

use Carbon\CarbonImmutable;

final readonly class PaymentRequestRecord
{
    /**
     * @param list<string> $eventTypes
     */
    public function __construct(
        public string $id,
        public string $requesterId,
        public string $currencyId,
        public string $currencyCode,
        public string $status,
        public string $exchangeRateSourceId,
        public string $exchangeRateSource,
        public string $title,
        public ?string $description,
        public string $amount,
        public string $eurExchangeRate,
        public string $amountEur,
        public CarbonImmutable $exchangeRateFetchedAt,
        public ?string $reviewedBy,
        public ?CarbonImmutable $reviewedAt,
        public ?string $reviewNote,
        public CarbonImmutable $expiresAt,
        public array $eventTypes,
    ) {
    }
}
