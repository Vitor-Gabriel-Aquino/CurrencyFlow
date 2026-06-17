<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Client;

class OAuthClientCorsOrigin extends Model
{
    use HasUuids;

    protected $table = 'oauth_client_cors_origins';

    protected $fillable = [
        'oauth_client_id',
        'origin',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'oauth_client_id');
    }
}
