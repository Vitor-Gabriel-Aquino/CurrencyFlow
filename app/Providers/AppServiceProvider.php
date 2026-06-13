<?php

namespace App\Providers;

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
        Passport::tokensCan([
            'payments:read' => 'Read payment requests',
            'payments:create' => 'Create payment requests',
            'payments:approve' => 'Approve or reject payment requests',
        ]);
    }
}
