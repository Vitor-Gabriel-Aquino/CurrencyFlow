<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
