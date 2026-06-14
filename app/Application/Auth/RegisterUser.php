<?php

namespace App\Application\Auth;

use App\Models\User;

class RegisterUser
{
    /**
     * @param array{name: string, email: string, password: string} $data
     */
    public function handle(array $data): User
    {
        return User::create($data);
    }
}
