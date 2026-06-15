<?php

namespace App\Http\Resources;

use App\Domain\PaymentRequests\Data\PaymentRequestRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PaymentRequestRecord $resource
 */
class PaymentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'requester_id' => $this->resource->requesterId,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'status' => $this->resource->status,
            'currency' => [
                'code' => $this->resource->currencyCode,
            ],
            'amount' => $this->resource->amount,
            'amount_eur' => $this->resource->amountEur,
            'exchange_rate' => [
                'base_currency_code' => 'EUR',
                'local_currency_code' => $this->resource->currencyCode,
                'eur_exchange_rate' => $this->resource->eurExchangeRate,
                'source' => $this->resource->exchangeRateSource,
                'fetched_at' => $this->resource->exchangeRateFetchedAt->toJSON(),
            ],
            'review' => [
                'reviewed_by' => $this->resource->reviewedBy,
                'reviewed_at' => $this->resource->reviewedAt?->toJSON(),
                'review_note' => $this->resource->reviewNote,
            ],
            'expires_at' => $this->resource->expiresAt->toJSON(),
            'events' => $this->resource->eventTypes,
        ];
    }
}
