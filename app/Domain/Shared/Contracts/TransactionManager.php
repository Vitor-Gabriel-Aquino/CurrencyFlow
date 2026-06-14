<?php

namespace App\Domain\Shared\Contracts;

interface TransactionManager
{
    /**
     * @template TReturn
     *
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    public function run(callable $callback): mixed;
}
