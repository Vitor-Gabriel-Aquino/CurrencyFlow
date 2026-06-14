<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $employeeRole = Role::query()->where('name', Role::EMPLOYEE)->firstOrFail();
        $financeRole = Role::query()->where('name', Role::FINANCE)->firstOrFail();

        $users = [
            ['Test Employee', 'test@example.com', $employeeRole->id, 'PT', 'EUR'],
            ['Ana Silva', 'ana.silva@example.com', $employeeRole->id, 'BR', 'BRL'],
            ['John Carter', 'john.carter@example.com', $employeeRole->id, 'US', 'USD'],
            ['Emily Brown', 'emily.brown@example.com', $employeeRole->id, 'GB', 'GBP'],
            ['Yuki Tanaka', 'yuki.tanaka@example.com', $employeeRole->id, 'JP', 'JPY'],
            ['Marta Kowalska', 'marta.kowalska@example.com', $financeRole->id, 'PL', 'PLN'],
        ];

        foreach ($users as [$name, $email, $roleId, $countryCode, $currencyCode]) {
            User::query()->updateOrCreate([
                'email' => $email,
            ], [
                'role_id' => $roleId,
                'country_id' => Country::query()->where('code', $countryCode)->firstOrFail()->id,
                'preferred_currency_id' => Currency::query()->where('code', $currencyCode)->firstOrFail()->id,
                'name' => $name,
                'password' => 'password',
            ]);
        }
    }
}
