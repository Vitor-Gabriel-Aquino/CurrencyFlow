<?php

namespace App\Application\PaymentRequests;

use App\Domain\PaymentRequests\Contracts\PaymentRequestRepository;
use App\Domain\PaymentRequests\Data\PaymentRequestRecord;
use App\Domain\Users\Enums\UserRole;
use App\Models\User;

class ShowPaymentRequest
{
    public function __construct(
        private readonly PaymentRequestRepository $paymentRequests,
    ) {
    }

    public function handle(User $user, string $paymentRequestId): ?PaymentRequestRecord
    {
        $paymentRequest = $this->paymentRequests->find($paymentRequestId);

        if (! $paymentRequest) {
            return null;
        }

        if ($user->hasRole(UserRole::Finance->value) || $paymentRequest->requesterId === $user->id) {
            return $paymentRequest;
        }

        return null;
    }
}
