<?php

namespace App\Application\PaymentRequests;

use App\Domain\PaymentRequests\Contracts\PaymentRequestRepository;
use Carbon\CarbonImmutable;

class ExpirePendingPaymentRequests
{
    public function __construct(
        private readonly PaymentRequestRepository $paymentRequests,
    ) {
    }

    public function handle(int $batchSize = 100): int
    {
        $expiredCount = 0;
        $now = CarbonImmutable::now('UTC');

        do {
            $paymentRequestIds = $this->paymentRequests->findExpiredPendingIds($now, $batchSize);

            foreach ($paymentRequestIds as $paymentRequestId) {
                if ($this->paymentRequests->expirePending($paymentRequestId, $now)) {
                    $expiredCount++;
                }
            }
        } while (count($paymentRequestIds) === $batchSize);

        return $expiredCount;
    }
}
