<?php

namespace Tests\Feature;

use App\Domain\PaymentRequests\Contracts\PaymentRequestRepository;
use App\Domain\PaymentRequests\Data\CreatePaymentRequestData;
use App\Domain\PaymentRequests\Data\PaymentRequestRecord;
use App\Domain\PaymentRequests\Data\ReviewPaymentRequestData;
use App\Domain\PaymentRequests\Enums\PaymentRequestEventType;
use App\Domain\PaymentRequests\Enums\PaymentRequestStatus;
use App\Models\Currency;
use App\Models\ExchangeRateSource;
use App\Models\PaymentRequestEvent;
use App\Models\PaymentRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePendingPaymentRequestsCommandTest extends TestCase
{
    use RefreshDatabase;

    private PaymentRequestRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $this->repository = $this->app->make(PaymentRequestRepository::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_command_expires_pending_payment_requests_in_batches(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:01'));

        $firstExpired = $this->createPaymentRequest('First expired request', '2026-06-16 10:00:00');
        $secondExpired = $this->createPaymentRequest('Second expired request', '2026-06-16 10:00:00');
        $futurePending = $this->createPaymentRequest('Future pending request', '2026-06-16 10:10:00');
        $approved = $this->createPaymentRequest('Approved request', '2026-06-16 10:00:00');

        $finance = User::query()->where('email', 'marta.kowalska@example.com')->firstOrFail();
        $this->repository->approvePending(
            $approved->id,
            new ReviewPaymentRequestData(
                reviewerId: $finance->id,
                reviewNote: 'Approved before expiration command.',
                reviewedAt: CarbonImmutable::parse('2026-06-15 10:00:00'),
            ),
        );

        $this->artisan('payment-requests:expire-pending', ['--batch' => 1])
            ->expectsOutput('Expired 2 pending payment request(s).')
            ->assertSuccessful();

        $this->assertPaymentRequestStatus($firstExpired->id, PaymentRequestStatus::Expired);
        $this->assertPaymentRequestStatus($secondExpired->id, PaymentRequestStatus::Expired);
        $this->assertPaymentRequestStatus($futurePending->id, PaymentRequestStatus::Pending);
        $this->assertPaymentRequestStatus($approved->id, PaymentRequestStatus::Approved);

        $this->assertPaymentRequestEvent($firstExpired->id, PaymentRequestEventType::Expired);
        $this->assertPaymentRequestEvent($secondExpired->id, PaymentRequestEventType::Expired);

    }

    public function test_command_rejects_invalid_batch_size(): void
    {
        $this->artisan('payment-requests:expire-pending', ['--batch' => 0])
            ->expectsOutput('The batch option must be at least 1.')
            ->assertFailed();
    }

    private function createPaymentRequest(string $title, string $expiresAt): PaymentRequestRecord
    {
        $employee = User::query()->where('email', 'ana.silva@example.com')->firstOrFail();
        $currency = Currency::query()->where('code', 'BRL')->firstOrFail();
        $exchangeRateSource = ExchangeRateSource::query()->where('name', 'ExchangeRate-API')->firstOrFail();

        return $this->repository->create(new CreatePaymentRequestData(
            requesterId: $employee->id,
            currencyId: $currency->id,
            exchangeRateSourceId: $exchangeRateSource->id,
            title: $title,
            description: 'Command test payment request.',
            amount: '123.45',
            eurExchangeRate: '5.88184850',
            amountEur: '20.99',
            exchangeRateFetchedAt: CarbonImmutable::parse('2026-06-14 10:00:00'),
            expiresAt: CarbonImmutable::parse($expiresAt),
        ));
    }

    private function assertPaymentRequestStatus(string $paymentRequestId, PaymentRequestStatus $status): void
    {
        $this->assertSame(
            $status->value,
            PaymentRequest::query()->findOrFail($paymentRequestId)->status->name,
        );
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
