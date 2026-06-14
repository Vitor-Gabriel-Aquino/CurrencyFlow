<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\UserRole;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        $this->seedCountries();
        $this->seedCurrencies();
    }

    private function seedRoles(): void
    {
        $roles = [
            [UserRole::Employee->value, 'Employee who can create payment requests.'],
            [UserRole::Finance->value, 'Finance team member who can approve or reject payment requests.'],
        ];

        foreach ($roles as [$name, $description]) {
            Role::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }

    private function seedCountries(): void
    {
        $countries = [
            ['PT', 'Portugal'],
            ['BR', 'Brazil'],
            ['US', 'United States'],
            ['GB', 'United Kingdom'],
            ['JP', 'Japan'],
            ['PL', 'Poland'],
        ];

        foreach ($countries as [$code, $name]) {
            Country::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name],
            );
        }
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['EUR', 'Euro', 2],
            ['BRL', 'Brazilian Real', 2],
            ['USD', 'United States Dollar', 2],
            ['GBP', 'Pound Sterling', 2],
            ['JPY', 'Japanese Yen', 0],
            ['PLN', 'Polish Zloty', 2],
        ];

        foreach ($currencies as [$code, $name, $exponent]) {
            Currency::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'exponent' => $exponent,
                ],
            );
        }
    }
}
