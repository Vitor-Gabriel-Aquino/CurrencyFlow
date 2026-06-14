<?php

namespace App\Domain\PaymentRequests\Enums;

enum PaymentRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
