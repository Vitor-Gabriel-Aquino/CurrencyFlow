<?php

namespace App\Domain\Users\Enums;

enum UserRole: string
{
    case Employee = 'employee';
    case Finance = 'finance';
}
