<?php

namespace App\Domain\PaymentRequests\Data;

use Carbon\CarbonImmutable;

final readonly class ReviewPaymentRequestData
{
    public function __construct(
        public string $reviewerId,
        public ?string $reviewNote,
        public CarbonImmutable $reviewedAt,
    ) {
    }
}
