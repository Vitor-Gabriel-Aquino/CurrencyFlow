<?php

namespace App\Application\Auth;

use App\Models\User;

class RevokeCurrentAccessToken
{
    public function handle(User $user): void
    {
        $user->token()->revoke();
    }
}
