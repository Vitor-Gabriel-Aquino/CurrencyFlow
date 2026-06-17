<?php

namespace App\Application\Auth;

use App\Models\User;

class UpdateCurrentUser
{
    /**
     * @param  array{name?: string, email?: string, country_id?: string, preferred_currency_id?: string, password?: string}  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->fill($data);
        $user->save();

        return $user->refresh();
    }
}
