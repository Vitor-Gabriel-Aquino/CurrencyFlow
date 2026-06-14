<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentRequest extends Model
{
    use HasUuids;

    private const IMMUTABLE_EXCHANGE_FIELDS = [
        'currency_id',
        'amount',
        'exchange_rate_source_id',
        'eur_exchange_rate',
        'amount_eur',
        'exchange_rate_fetched_at',
    ];

    protected $fillable = [
        'requester_id',
        'currency_id',
        'status_id',
        'exchange_rate_source_id',
        'title',
        'description',
        'amount',
        'eur_exchange_rate',
        'amount_eur',
        'exchange_rate_fetched_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'eur_exchange_rate' => 'decimal:8',
            'amount_eur' => 'decimal:4',
            'exchange_rate_fetched_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PaymentRequest $paymentRequest): void {
            if ($paymentRequest->isDirty(self::IMMUTABLE_EXCHANGE_FIELDS)) {
                throw new LogicException('Payment request exchange rate data cannot be changed after creation.');
            }
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(PaymentRequestStatus::class, 'status_id');
    }

    public function exchangeRateSource(): BelongsTo
    {
        return $this->belongsTo(ExchangeRateSource::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentRequestEvent::class);
    }
}
