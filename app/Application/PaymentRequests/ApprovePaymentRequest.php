<?php

namespace App\Application\PaymentRequests;

use App\Domain\PaymentRequests\Contracts\PaymentRequestRepository;
use App\Domain\PaymentRequests\Data\PaymentRequestRecord;
use App\Domain\PaymentRequests\Data\ReviewPaymentRequestData;
use App\Models\User;
use Carbon\CarbonImmutable;

class ApprovePaymentRequest
{
    public function __construct(
        private readonly PaymentRequestRepository $paymentRequests,
    ) {
    }

    public function handle(string $paymentRequestId, User $reviewer, ?string $reviewNote = null): ?PaymentRequestRecord
    {
        return $this->paymentRequests->approvePending(
            $paymentRequestId,
            new ReviewPaymentRequestData(
                reviewerId: $reviewer->id,
                reviewNote: $reviewNote,
                reviewedAt: CarbonImmutable::now('UTC'),
            ),
        );
    }
}
