<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRequestEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_request_id',
        'actor_id',
        'event_type_id',
        'from_status_id',
        'to_status_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(PaymentRequestEventType::class, 'event_type_id');
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(PaymentRequestStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(PaymentRequestStatus::class, 'to_status_id');
    }
}
