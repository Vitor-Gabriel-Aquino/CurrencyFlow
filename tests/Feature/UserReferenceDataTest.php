<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_employee_and_finance_users_with_reference_data(): void
    {
        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $this->assertDatabaseHas('roles', ['name' => Role::EMPLOYEE]);
        $this->assertDatabaseHas('roles', ['name' => Role::FINANCE]);
        $this->assertDatabaseCount('countries', 6);
        $this->assertDatabaseCount('currencies', 6);

        $this->assertSame(5, User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::EMPLOYEE))
            ->count());

        $this->assertSame(1, User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::FINANCE))
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
            ->assertJsonPath('data.0.code', 'BR')
            ->assertJsonPath('data.0.name', 'Brazil')
            ->assertJsonMissingPath('data.0.id');
    }

    public function test_currencies_can_be_listed_for_registration(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->getJson('/api/currencies')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'BRL')
            ->assertJsonPath('data.0.name', 'Brazilian Real')
            ->assertJsonPath('data.0.exponent', 2)
            ->assertJsonMissingPath('data.0.id');
    }
}
