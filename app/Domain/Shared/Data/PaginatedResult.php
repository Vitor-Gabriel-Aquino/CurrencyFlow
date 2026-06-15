<?php

namespace App\Domain\Shared\Data;

final readonly class PaginatedResult
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {
    }
}
