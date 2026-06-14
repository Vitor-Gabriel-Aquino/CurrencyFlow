<?php

namespace App\Domain\PaymentRequests\Enums;

enum PaymentRequestEventType: string
{
    case Created = 'created';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
