<?php

namespace Database\Seeders;

use App\Domain\PaymentRequests\Enums\PaymentRequestEventType;
use App\Domain\PaymentRequests\Enums\PaymentRequestStatus;
use App\Domain\Users\Enums\UserRole;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ExchangeRateSource;
use App\Models\PaymentRequestEventType as PaymentRequestEventTypeModel;
use App\Models\PaymentRequestStatus as PaymentRequestStatusModel;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        $this->seedCountries();
        $this->seedCurrencies();
        $this->seedPaymentRequestStatuses();
        $this->seedPaymentRequestEventTypes();
        $this->seedExchangeRateSources();
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
        $countries = config('reference_data.countries', []);

        foreach ($countries as [$code, $name]) {
            Country::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name],
            );
        }
    }

    private function seedCurrencies(): void
    {
        $currencies = config('reference_data.currencies', []);

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

    private function seedPaymentRequestStatuses(): void
    {
        $statuses = [
            [PaymentRequestStatus::Pending->value, 'Payment request is waiting for finance review.'],
            [PaymentRequestStatus::Approved->value, 'Payment request was approved by finance.'],
            [PaymentRequestStatus::Rejected->value, 'Payment request was rejected by finance.'],
            [PaymentRequestStatus::Expired->value, 'Payment request expired before finance review.'],
        ];

        foreach ($statuses as [$name, $description]) {
            PaymentRequestStatusModel::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }

    private function seedPaymentRequestEventTypes(): void
    {
        $eventTypes = [
            [PaymentRequestEventType::Created->value, 'Payment request was created.'],
            [PaymentRequestEventType::Approved->value, 'Payment request was approved.'],
            [PaymentRequestEventType::Rejected->value, 'Payment request was rejected.'],
            [PaymentRequestEventType::Expired->value, 'Payment request expired.'],
        ];

        foreach ($eventTypes as [$name, $description]) {
            PaymentRequestEventTypeModel::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }

    private function seedExchangeRateSources(): void
    {
        ExchangeRateSource::query()->updateOrCreate(
            ['name' => 'ExchangeRate-API'],
            [
                'description' => 'External exchange rate provider used to convert local currencies to EUR.',
                'base_url' => 'https://www.exchangerate-api.com',
            ],
        );
    }
}
