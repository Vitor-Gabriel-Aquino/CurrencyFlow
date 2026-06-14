<?php

namespace App\Domain\PaymentRequests\Contracts;

use App\Domain\PaymentRequests\Data\CreatePaymentRequestData;
use App\Domain\PaymentRequests\Data\PaymentRequestRecord;
use App\Domain\PaymentRequests\Data\ReviewPaymentRequestData;
use Carbon\CarbonImmutable;

interface PaymentRequestRepository
{
    public function create(CreatePaymentRequestData $data): PaymentRequestRecord;

    public function approvePending(string $paymentRequestId, ReviewPaymentRequestData $data): ?PaymentRequestRecord;

    public function rejectPending(string $paymentRequestId, ReviewPaymentRequestData $data): ?PaymentRequestRecord;

    public function expirePending(string $paymentRequestId, CarbonImmutable $now): ?PaymentRequestRecord;
}
