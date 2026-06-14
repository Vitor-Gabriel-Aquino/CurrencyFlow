<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::authorizationView('auth.authorize');

        Passport::tokensCan([
            'payments:read' => 'Read payment requests',
            'payments:create' => 'Create payment requests',
            'payments:approve' => 'Approve or reject payment requests',
        ]);

        Gate::define('perform-finance-actions', fn (User $user): bool => $user->hasRole(Role::FINANCE));
    }
}
