<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\UserRole;
use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_seeder_creates_roles_countries_and_currencies(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => UserRole::Employee->value]);
        $this->assertDatabaseHas('roles', ['name' => UserRole::Finance->value]);
        $this->assertGreaterThanOrEqual(160, Country::query()->count());
        $this->assertSame(162, Currency::query()->count());
        $this->assertDatabaseHas('countries', ['code' => 'BR', 'name' => 'Brazil']);
        $this->assertDatabaseHas('countries', ['code' => 'PT', 'name' => 'Portugal']);
        $this->assertDatabaseHas('countries', ['code' => 'US', 'name' => 'United States']);
        $this->assertDatabaseHas('currencies', ['code' => 'BRL', 'name' => 'Brazilian Real', 'exponent' => 2]);
        $this->assertDatabaseHas('currencies', ['code' => 'EUR', 'name' => 'Euro', 'exponent' => 2]);
        $this->assertDatabaseHas('currencies', ['code' => 'JPY', 'name' => 'Japanese Yen', 'exponent' => 0]);
        $this->assertDatabaseHas('currencies', ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'exponent' => 3]);
    }

    public function test_user_seeder_creates_employee_and_finance_users(): void
    {
        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $this->assertSame(5, User::query()
            ->whereHas('role', fn ($query) => $query->where('name', UserRole::Employee->value))
            ->count());

        $this->assertSame(1, User::query()
            ->whereHas('role', fn ($query) => $query->where('name', UserRole::Finance->value))
            ->count());
    }

    public function test_only_finance_users_can_perform_finance_actions(): void
    {
        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $employee = User::query()->where('email', 'test@example.com')->firstOrFail();
        $finance = User::query()->where('email', 'marta.kowalska@example.com')->firstOrFail();

        $this->assertFalse(Gate::forUser($employee)->allows('perform-finance-actions'));
        $this->assertTrue(Gate::forUser($finance)->allows('perform-finance-actions'));
    }

    public function test_countries_can_be_listed_for_registration(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->getJson('/api/countries')
            ->assertOk()
            ->assertJsonFragment([
                'code' => 'BR',
                'name' => 'Brazil',
            ])
            ->assertJsonMissingPath('data.0.id');
    }

    public function test_currencies_can_be_listed_for_registration(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->getJson('/api/currencies')
            ->assertOk()
            ->assertJsonFragment([
                'code' => 'BRL',
                'name' => 'Brazilian Real',
                'exponent' => 2,
            ])
            ->assertJsonMissingPath('data.0.id');
    }
}
