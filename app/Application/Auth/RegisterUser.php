<?php

namespace App\Application\Auth;

use App\Models\User;

class RegisterUser
{
    /**
     * @param array{role_id: string, country_id: string, preferred_currency_id: string, name: string, email: string, password: string} $data
     */
    public function handle(array $data): User
    {
        return User::create($data);
    }
}
