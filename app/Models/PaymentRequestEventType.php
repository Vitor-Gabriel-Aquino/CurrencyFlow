<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentRequestEventType extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(PaymentRequestEvent::class, 'event_type_id');
    }
}
