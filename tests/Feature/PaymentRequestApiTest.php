<?php

namespace Tests\Feature;

use App\Domain\ExchangeRates\Contracts\ExchangeRateProvider;
use App\Domain\ExchangeRates\Data\ExchangeRateQuote;
use App\Domain\ExchangeRates\Exceptions\ExchangeRateProviderException;
use App\Domain\PaymentRequests\Enums\PaymentRequestEventType;
use App\Models\PaymentRequestEvent;
use App\Domain\Shared\ValueObjects\CurrencyCode;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PaymentRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $this->bindExchangeRateProvider();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_payment_request_creation_requires_authentication(): void
    {
        $this->postJson('/api/payment-requests', $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_payment_request_listing_requires_authentication(): void
    {
        $this->getJson('/api/payment-requests')
            ->assertUnauthorized();
    }

    public function test_payment_request_detail_requires_authentication(): void
    {
        $this->getJson('/api/payment-requests/00000000-0000-0000-0000-000000000000')
            ->assertUnauthorized();
    }

    public function test_employee_can_create_payment_request_with_create_scope(): void
    {
        Passport::actingAs($this->employee(), ['payments:create'], 'api');

        $this->postJson('/api/payment-requests', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Conference reimbursement')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.currency.code', 'BRL')
            ->assertJsonMissingPath('data.currency.id')
            ->assertJsonPath('data.amount', '123.4500')
            ->assertJsonPath('data.amount_eur', '20.9883')
            ->assertJsonPath('data.exchange_rate.base_currency_code', 'EUR')
            ->assertJsonPath('data.exchange_rate.local_currency_code', 'BRL')
            ->assertJsonPath('data.exchange_rate.eur_exchange_rate', '5.88184850')
            ->assertJsonPath('data.exchange_rate.source', 'ExchangeRate-API');

        $this->assertDatabaseHas('payment_requests', [
            'title' => 'Conference reimbursement',
            'amount' => '123.4500',
            'amount_eur' => '20.9883',
            'eur_exchange_rate' => '5.88184850',
        ]);
    }

    public function test_payment_request_creation_validates_payload(): void
    {
        Passport::actingAs($this->employee(), ['payments:create'], 'api');

        $this->postJson('/api/payment-requests', [
            'title' => '',
            'amount' => '-10',
            'currency_code' => 'ZZZ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'amount', 'currency_code']);
    }

    public function test_payment_request_creation_requires_create_scope(): void
    {
        Passport::actingAs($this->employee(), ['payments:read'], 'api');

        $this->postJson('/api/payment-requests', $this->validPayload())
            ->assertForbidden();
    }

    public function test_payment_request_listing_requires_read_scope(): void
    {
        Passport::actingAs($this->employee(), ['payments:create'], 'api');

        $this->getJson('/api/payment-requests')
            ->assertForbidden();
    }

    public function test_payment_requests_can_be_listed_with_status_filter(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $this->postJson('/api/payment-requests', $this->validPayload(['title' => 'First request']))->assertCreated();

        Passport::actingAs($employee, ['payments:read'], 'api');

        $this->getJson('/api/payment-requests?status=pending&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'First request')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_payment_request_detail_can_be_read_with_read_scope(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload(['title' => 'Detail request']))
            ->assertCreated()
            ->json('data.id');

        Passport::actingAs($employee, ['payments:read'], 'api');

        $this->getJson('/api/payment-requests/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.title', 'Detail request')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_payment_request_detail_requires_read_scope(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        $this->getJson('/api/payment-requests/'.$id)
            ->assertForbidden();
    }

    public function test_finance_can_list_all_payment_requests_while_employee_lists_only_their_own(): void
    {
        $firstEmployee = $this->employee();
        $secondEmployee = User::query()->where('email', 'john.carter@example.com')->firstOrFail();

        Passport::actingAs($firstEmployee, ['payments:create'], 'api');
        $this->postJson('/api/payment-requests', $this->validPayload(['title' => 'First employee request']))->assertCreated();

        Passport::actingAs($secondEmployee, ['payments:create'], 'api');
        $this->postJson('/api/payment-requests', $this->validPayload(['title' => 'Second employee request']))->assertCreated();

        Passport::actingAs($firstEmployee, ['payments:read'], 'api');
        $this->getJson('/api/payment-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'First employee request')
            ->assertJsonPath('meta.total', 1);

        Passport::actingAs($this->finance(), ['payments:read'], 'api');
        $this->getJson('/api/payment-requests')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_employee_cannot_read_another_employee_payment_request(): void
    {
        $firstEmployee = $this->employee();
        $secondEmployee = User::query()->where('email', 'john.carter@example.com')->firstOrFail();

        Passport::actingAs($firstEmployee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        Passport::actingAs($secondEmployee, ['payments:read'], 'api');

        $this->getJson('/api/payment-requests/'.$id)
            ->assertNotFound();
    }

    public function test_finance_can_approve_pending_payment_request(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        Passport::actingAs($this->finance(), ['payments:approve'], 'api');

        $this->postJson('/api/payment-requests/'.$id.'/approval', [
            'review_note' => 'Approved for reimbursement.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.review.review_note', 'Approved for reimbursement.');

        $this->assertPaymentRequestEvent($id, PaymentRequestEventType::Approved);
    }

    public function test_payment_request_approval_requires_approve_scope(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        Passport::actingAs($this->finance(), ['payments:read'], 'api');

        $this->postJson('/api/payment-requests/'.$id.'/approval')
            ->assertForbidden();
    }

    public function test_finance_can_reject_pending_payment_request(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        Passport::actingAs($this->finance(), ['payments:approve'], 'api');

        $this->postJson('/api/payment-requests/'.$id.'/rejection', [
            'review_note' => 'Missing receipt.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.review.review_note', 'Missing receipt.');

        $this->assertPaymentRequestEvent($id, PaymentRequestEventType::Rejected);
    }

    public function test_payment_request_rejection_requires_approve_scope(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        Passport::actingAs($this->finance(), ['payments:read'], 'api');

        $this->postJson('/api/payment-requests/'.$id.'/rejection')
            ->assertForbidden();
    }

    public function test_expired_payment_request_cannot_be_approved_even_before_expiration_command_runs(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-14 10:00:00'));

        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:01'));
        Passport::actingAs($this->finance(), ['payments:approve'], 'api');

        $this->postJson('/api/payment-requests/'.$id.'/approval')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only unexpired pending payment requests can be approved.');

    }

    public function test_expired_payment_request_cannot_be_rejected_even_before_expiration_command_runs(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-14 10:00:00'));

        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:01'));
        Passport::actingAs($this->finance(), ['payments:approve'], 'api');

        $this->postJson('/api/payment-requests/'.$id.'/rejection')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only unexpired pending payment requests can be rejected.');

    }

    public function test_non_finance_user_cannot_approve_payment_request(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        Passport::actingAs($employee, ['payments:approve'], 'api');

        $this->postJson('/api/payment-requests/'.$id.'/approval')
            ->assertForbidden();
    }

    public function test_non_finance_user_cannot_reject_payment_request(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        Passport::actingAs($employee, ['payments:approve'], 'api');

        $this->postJson('/api/payment-requests/'.$id.'/rejection')
            ->assertForbidden();
    }

    public function test_finalized_payment_request_cannot_be_rejected_again(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        Passport::actingAs($this->finance(), ['payments:approve'], 'api');
        $this->postJson('/api/payment-requests/'.$id.'/approval')->assertOk();

        $this->postJson('/api/payment-requests/'.$id.'/rejection')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only unexpired pending payment requests can be rejected.');
    }

    public function test_finalized_payment_request_cannot_be_approved_again(): void
    {
        $employee = $this->employee();
        Passport::actingAs($employee, ['payments:create'], 'api');
        $id = $this->postJson('/api/payment-requests', $this->validPayload())->assertCreated()->json('data.id');

        Passport::actingAs($this->finance(), ['payments:approve'], 'api');
        $this->postJson('/api/payment-requests/'.$id.'/rejection')->assertOk();

        $this->postJson('/api/payment-requests/'.$id.'/approval')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only unexpired pending payment requests can be approved.');
    }

    public function test_exchange_rate_provider_failure_returns_service_unavailable(): void
    {
        $this->app->bind(ExchangeRateProvider::class, fn (): ExchangeRateProvider => new class implements ExchangeRateProvider {
            public function getEurExchangeRate(CurrencyCode $localCurrencyCode): ExchangeRateQuote
            {
                throw ExchangeRateProviderException::unavailable();
            }
        });

        Passport::actingAs($this->employee(), ['payments:create'], 'api');

        $this->postJson('/api/payment-requests', $this->validPayload())
            ->assertStatus(503)
            ->assertJsonPath('message', 'Exchange rate provider is unavailable.');
    }

    private function bindExchangeRateProvider(): void
    {
        $this->app->bind(ExchangeRateProvider::class, fn (): ExchangeRateProvider => new class implements ExchangeRateProvider {
            public function getEurExchangeRate(CurrencyCode $localCurrencyCode): ExchangeRateQuote
            {
                return new ExchangeRateQuote(
                    baseCurrencyCode: 'EUR',
                    localCurrencyCode: $localCurrencyCode->value,
                    eurExchangeRate: '5.88184850',
                    source: 'ExchangeRate-API',
                    fetchedAt: CarbonImmutable::parse('2026-06-14 10:00:00'),
                );
            }
        });
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            ...[
                'title' => 'Conference reimbursement',
                'description' => 'Hotel and local transportation costs.',
                'amount' => '123.45',
                'currency_code' => 'BRL',
            ],
            ...$overrides,
        ];
    }

    private function employee(): User
    {
        return User::query()->where('email', 'ana.silva@example.com')->firstOrFail();
    }

    private function finance(): User
    {
        return User::query()->where('email', 'marta.kowalska@example.com')->firstOrFail();
    }

    private function assertPaymentRequestEvent(string $paymentRequestId, PaymentRequestEventType $eventType): void
    {
        $this->assertTrue(
            PaymentRequestEvent::query()
                ->where('payment_request_id', $paymentRequestId)
                ->whereHas('eventType', fn ($query) => $query->where('name', $eventType->value))
                ->exists(),
        );
    }
}
