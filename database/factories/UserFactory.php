<?php

namespace Database\Factories;

use App\Domain\Users\Enums\UserRole;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::query()->firstOrCreate([
                'name' => UserRole::Employee->value,
            ], [
                'description' => 'Employee who can create payment requests.',
            ])->id,
            'country_id' => Country::query()->firstOrCreate([
                'code' => 'PT',
            ], [
                'name' => 'Portugal',
            ])->id,
            'preferred_currency_id' => Currency::query()->firstOrCreate([
                'code' => 'EUR',
            ], [
                'name' => 'Euro',
                'exponent' => 2,
            ])->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
