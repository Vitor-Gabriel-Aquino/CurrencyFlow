<?php

namespace App\Application\PaymentRequests;

use App\Domain\PaymentRequests\Contracts\PaymentRequestRepository;
use App\Domain\Shared\Data\PaginatedResult;
use App\Domain\Users\Enums\UserRole;
use App\Models\User;

class ListPaymentRequests
{
    public function __construct(
        private readonly PaymentRequestRepository $paymentRequests,
    ) {
    }

    public function handle(User $user, ?string $status = null, int $page = 1, int $perPage = 15): PaginatedResult
    {
        $requesterId = $user->hasRole(UserRole::Finance->value) ? null : $user->id;

        return $this->paymentRequests->list($requesterId, $status, $page, $perPage);
    }
}
