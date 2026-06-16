<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\PaymentRequests\Contracts\PaymentRequestRepository;
use App\Domain\PaymentRequests\Data\CreatePaymentRequestData;
use App\Domain\PaymentRequests\Data\PaymentRequestRecord;
use App\Domain\PaymentRequests\Data\ReviewPaymentRequestData;
use App\Domain\PaymentRequests\Enums\PaymentRequestEventType;
use App\Domain\PaymentRequests\Enums\PaymentRequestStatus;
use App\Domain\Shared\Contracts\TransactionManager;
use App\Domain\Shared\Data\PaginatedResult;
use App\Models\PaymentRequest;
use App\Models\PaymentRequestEventType as PaymentRequestEventTypeModel;
use App\Models\PaymentRequestStatus as PaymentRequestStatusModel;
use Carbon\CarbonImmutable;

class EloquentPaymentRequestRepository implements PaymentRequestRepository
{
    public function __construct(
        private readonly TransactionManager $transactions,
    ) {
    }

    public function create(CreatePaymentRequestData $data): PaymentRequestRecord
    {
        return $this->transactions->run(function () use ($data): PaymentRequestRecord {
            $paymentRequest = PaymentRequest::query()->create([
                'requester_id' => $data->requesterId,
                'currency_id' => $data->currencyId,
                'status_id' => $this->statusId(PaymentRequestStatus::Pending),
                'exchange_rate_source_id' => $data->exchangeRateSourceId,
                'title' => $data->title,
                'description' => $data->description,
                'amount' => $data->amount,
                'eur_exchange_rate' => $data->eurExchangeRate,
                'amount_eur' => $data->amountEur,
                'exchange_rate_fetched_at' => $data->exchangeRateFetchedAt,
                'expires_at' => $data->expiresAt,
            ]);

            $paymentRequest->events()->create([
                'actor_id' => $data->requesterId,
                'event_type_id' => $this->eventTypeId(PaymentRequestEventType::Created),
                'metadata' => [
                    'status' => PaymentRequestStatus::Pending->value,
                ],
            ]);

            return $this->toRecord($paymentRequest);
        });
    }

    public function list(?string $requesterId = null, ?string $status = null, int $page = 1, int $perPage = 15): PaginatedResult
    {
        $paginator = PaymentRequest::query()
            ->with(['currency', 'status', 'exchangeRateSource', 'events.eventType'])
            ->when($requesterId, fn ($query) => $query->where('requester_id', $requesterId))
            ->when($status, fn ($query) => $query->whereHas('status', fn ($statusQuery) => $statusQuery->where('name', $status)))
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return new PaginatedResult(
            items: $paginator->getCollection()
                ->map(fn (PaymentRequest $paymentRequest): PaymentRequestRecord => $this->toRecord($paymentRequest))
                ->values()
                ->all(),
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            lastPage: $paginator->lastPage(),
        );
    }

    public function find(string $paymentRequestId): ?PaymentRequestRecord
    {
        $paymentRequest = PaymentRequest::query()
            ->with(['currency', 'status', 'exchangeRateSource', 'events.eventType'])
            ->whereKey($paymentRequestId)
            ->first();

        return $paymentRequest ? $this->toRecord($paymentRequest) : null;
    }

    public function findExpiredPendingIds(CarbonImmutable $now, int $limit): array
    {
        return PaymentRequest::query()
            ->where('status_id', $this->statusId(PaymentRequestStatus::Pending))
            ->where('expires_at', '<=', $now)
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    public function approvePending(string $paymentRequestId, ReviewPaymentRequestData $data): ?PaymentRequestRecord
    {
        return $this->reviewPending(
            $paymentRequestId,
            PaymentRequestStatus::Approved,
            PaymentRequestEventType::Approved,
            $data,
        );
    }

    public function rejectPending(string $paymentRequestId, ReviewPaymentRequestData $data): ?PaymentRequestRecord
    {
        return $this->reviewPending(
            $paymentRequestId,
            PaymentRequestStatus::Rejected,
            PaymentRequestEventType::Rejected,
            $data,
        );
    }

    public function expirePending(string $paymentRequestId, CarbonImmutable $now): ?PaymentRequestRecord
    {
        return $this->transactions->run(function () use ($paymentRequestId, $now): ?PaymentRequestRecord {
            $paymentRequest = $this->pendingPaymentRequest($paymentRequestId);

            if (! $paymentRequest) {
                return null;
            }

            if ($paymentRequest->expires_at->isAfter($now)) {
                return null;
            }

            $paymentRequest->forceFill([
                'status_id' => $this->statusId(PaymentRequestStatus::Expired),
            ])->save();

            $paymentRequest->events()->create([
                'actor_id' => null,
                'event_type_id' => $this->eventTypeId(PaymentRequestEventType::Expired),
                'metadata' => [
                    'status' => PaymentRequestStatus::Expired->value,
                ],
            ]);

            return $this->toRecord($paymentRequest);
        });
    }

    private function reviewPending(
        string $paymentRequestId,
        PaymentRequestStatus $targetStatus,
        PaymentRequestEventType $eventType,
        ReviewPaymentRequestData $data,
    ): ?PaymentRequestRecord {
        return $this->transactions->run(function () use ($paymentRequestId, $targetStatus, $eventType, $data): ?PaymentRequestRecord {
            $paymentRequest = $this->pendingPaymentRequest($paymentRequestId);

            if (! $paymentRequest) {
                return null;
            }

            if ($paymentRequest->expires_at->lessThanOrEqualTo($data->reviewedAt)) {
                return null;
            }

            $paymentRequest->forceFill([
                'status_id' => $this->statusId($targetStatus),
                'reviewed_by' => $data->reviewerId,
                'reviewed_at' => $data->reviewedAt,
                'review_note' => $data->reviewNote,
            ])->save();

            $paymentRequest->events()->create([
                'actor_id' => $data->reviewerId,
                'event_type_id' => $this->eventTypeId($eventType),
                'metadata' => [
                    'status' => $targetStatus->value,
                    'review_note' => $data->reviewNote,
                ],
            ]);

            return $this->toRecord($paymentRequest);
        });
    }

    private function pendingPaymentRequest(string $paymentRequestId): ?PaymentRequest
    {
        $paymentRequest = PaymentRequest::query()
            ->whereKey($paymentRequestId)
            ->lockForUpdate()
            ->first();

        if (! $paymentRequest) {
            return null;
        }

        return $paymentRequest->status_id === $this->statusId(PaymentRequestStatus::Pending)
            ? $paymentRequest
            : null;
    }

    private function statusId(PaymentRequestStatus $status): string
    {
        return PaymentRequestStatusModel::query()
            ->where('name', $status->value)
            ->firstOrFail()
            ->id;
    }

    private function eventTypeId(PaymentRequestEventType $eventType): string
    {
        return PaymentRequestEventTypeModel::query()
            ->where('name', $eventType->value)
            ->firstOrFail()
            ->id;
    }

    private function toRecord(PaymentRequest $paymentRequest): PaymentRequestRecord
    {
        $paymentRequest->loadMissing(['currency', 'status', 'exchangeRateSource', 'events.eventType']);

        return new PaymentRequestRecord(
            id: $paymentRequest->id,
            requesterId: $paymentRequest->requester_id,
            currencyId: $paymentRequest->currency_id,
            currencyCode: $paymentRequest->currency->code,
            status: $paymentRequest->status->name,
            exchangeRateSourceId: $paymentRequest->exchange_rate_source_id,
            exchangeRateSource: $paymentRequest->exchangeRateSource->name,
            title: $paymentRequest->title,
            description: $paymentRequest->description,
            amount: $paymentRequest->amount,
            eurExchangeRate: $paymentRequest->eur_exchange_rate,
            amountEur: $paymentRequest->amount_eur,
            exchangeRateFetchedAt: $paymentRequest->exchange_rate_fetched_at,
            reviewedBy: $paymentRequest->reviewed_by,
            reviewedAt: $paymentRequest->reviewed_at,
            reviewNote: $paymentRequest->review_note,
            expiresAt: $paymentRequest->expires_at,
            eventTypes: $paymentRequest->events
                ->map(fn ($event): string => $event->eventType->name)
                ->values()
                ->all(),
        );
    }
}
