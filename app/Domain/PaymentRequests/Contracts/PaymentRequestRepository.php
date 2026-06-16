<?php

namespace App\Domain\PaymentRequests\Contracts;

use App\Domain\PaymentRequests\Data\CreatePaymentRequestData;
use App\Domain\PaymentRequests\Data\PaymentRequestRecord;
use App\Domain\PaymentRequests\Data\ReviewPaymentRequestData;
use App\Domain\Shared\Data\PaginatedResult;
use Carbon\CarbonImmutable;

interface PaymentRequestRepository
{
    public function create(CreatePaymentRequestData $data): PaymentRequestRecord;

    public function list(?string $requesterId = null, ?string $status = null, int $page = 1, int $perPage = 15): PaginatedResult;

    public function find(string $paymentRequestId): ?PaymentRequestRecord;

    /**
     * @return string[]
     */
    public function findExpiredPendingIds(CarbonImmutable $now, int $limit): array;

    public function approvePending(string $paymentRequestId, ReviewPaymentRequestData $data): ?PaymentRequestRecord;

    public function rejectPending(string $paymentRequestId, ReviewPaymentRequestData $data): ?PaymentRequestRecord;

    public function expirePending(string $paymentRequestId, CarbonImmutable $now): ?PaymentRequestRecord;
}
