<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Contracts\TransactionManager;
use Illuminate\Support\Facades\DB;

class DatabaseTransactionManager implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
