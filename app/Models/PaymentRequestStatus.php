<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentRequestStatus extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
    ];

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class, 'status_id');
    }
}
