<?php

namespace App\Providers;

use App\Domain\ExchangeRates\Contracts\ExchangeRateProvider;
use App\Domain\PaymentRequests\Contracts\PaymentRequestRepository;
use App\Domain\Shared\Contracts\TransactionManager;
use App\Domain\Users\Enums\UserRole;
use App\Infrastructure\ExchangeRates\ExchangeRateApiProvider;
use App\Infrastructure\Persistence\DatabaseTransactionManager;
use App\Infrastructure\Persistence\Eloquent\EloquentPaymentRequestRepository;
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
        $this->app->bind(TransactionManager::class, DatabaseTransactionManager::class);
        $this->app->bind(PaymentRequestRepository::class, EloquentPaymentRequestRepository::class);
        $this->app->bind(ExchangeRateProvider::class, ExchangeRateApiProvider::class);
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

        Gate::define('perform-finance-actions', fn (User $user): bool => $user->hasRole(UserRole::Finance->value));
        Gate::define('manage-oauth-clients', fn (User $user): bool => $user->hasRole(UserRole::Finance->value));
    }
}
