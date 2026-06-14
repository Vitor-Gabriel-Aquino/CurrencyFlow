<?php

namespace Tests\Feature;

use App\Domain\PaymentRequests\Contracts\PaymentRequestRepository;
use App\Domain\PaymentRequests\Data\CreatePaymentRequestData;
use App\Domain\PaymentRequests\Data\PaymentRequestRecord;
use App\Domain\PaymentRequests\Data\ReviewPaymentRequestData;
use App\Domain\PaymentRequests\Enums\PaymentRequestEventType;
use App\Domain\PaymentRequests\Enums\PaymentRequestStatus;
use App\Infrastructure\Persistence\Eloquent\EloquentPaymentRequestRepository;
use App\Models\Currency;
use App\Models\ExchangeRateSource;
use App\Models\PaymentRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PaymentRequestPersistenceTest extends TestCase
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

    public function test_payment_request_repository_is_bound_to_eloquent_implementation(): void
    {
        $this->assertInstanceOf(EloquentPaymentRequestRepository::class, $this->repository);
    }

    public function test_payment_request_is_persisted_with_exchange_rate_snapshot_and_created_event(): void
    {
        $paymentRequest = $this->createPaymentRequest();

        $this->assertSame(PaymentRequestStatus::Pending->value, $paymentRequest->status);
        $this->assertSame('BRL', $paymentRequest->currencyCode);
        $this->assertSame('123.4500', $paymentRequest->amount);
        $this->assertSame('5.88184850', $paymentRequest->eurExchangeRate);
        $this->assertSame('20.9900', $paymentRequest->amountEur);
        $this->assertSame('ExchangeRate-API', $paymentRequest->exchangeRateSource);
        $this->assertSame('2026-06-14 10:00:00', $paymentRequest->exchangeRateFetchedAt->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('payment_request_events', [
            'payment_request_id' => $paymentRequest->id,
        ]);

        $this->assertSame([PaymentRequestEventType::Created->value], $paymentRequest->eventTypes);
    }

    public function test_exchange_rate_snapshot_cannot_be_changed_after_creation(): void
    {
        $paymentRequest = $this->createPaymentRequest();

        $paymentRequestModel = PaymentRequest::query()->findOrFail($paymentRequest->id);
        $paymentRequestModel->eur_exchange_rate = '999.00000000';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Payment request exchange rate data cannot be changed after creation.');

        $paymentRequestModel->save();
    }

    public function test_finance_review_can_only_approve_pending_payment_requests(): void
    {
        $paymentRequest = $this->createPaymentRequest();
        $finance = User::query()->where('email', 'marta.kowalska@example.com')->firstOrFail();

        $approvedPaymentRequest = $this->repository->approvePending(
            $paymentRequest->id,
            new ReviewPaymentRequestData(
                reviewerId: $finance->id,
                reviewNote: 'Approved for reimbursement.',
                reviewedAt: CarbonImmutable::parse('2026-06-14 11:00:00'),
            ),
        );

        $this->assertNotNull($approvedPaymentRequest);
        $this->assertSame(PaymentRequestStatus::Approved->value, $approvedPaymentRequest->status);
        $this->assertSame($finance->id, $approvedPaymentRequest->reviewedBy);
        $this->assertSame('Approved for reimbursement.', $approvedPaymentRequest->reviewNote);

        $rejectedPaymentRequest = $this->repository->rejectPending(
            $paymentRequest->id,
            new ReviewPaymentRequestData(
                reviewerId: $finance->id,
                reviewNote: 'Trying to reject an already approved request.',
                reviewedAt: CarbonImmutable::parse('2026-06-14 12:00:00'),
            ),
        );

        $this->assertNull($rejectedPaymentRequest);
        $this->assertSame(
            PaymentRequestStatus::Approved->value,
            PaymentRequest::query()->findOrFail($paymentRequest->id)->status->name,
        );
    }

    public function test_expiration_can_only_change_pending_payment_requests(): void
    {
        $paymentRequest = $this->createPaymentRequest();
        $expiredPaymentRequest = $this->repository->expirePending(
            $paymentRequest->id,
            CarbonImmutable::parse('2026-06-16 10:00:01'),
        );

        $this->assertNotNull($expiredPaymentRequest);
        $this->assertSame(PaymentRequestStatus::Expired->value, $expiredPaymentRequest->status);

        $secondExpirationAttempt = $this->repository->expirePending(
            $paymentRequest->id,
            CarbonImmutable::parse('2026-06-16 10:00:02'),
        );

        $this->assertNull($secondExpirationAttempt);
        $this->assertSame(
            PaymentRequestStatus::Expired->value,
            PaymentRequest::query()->findOrFail($paymentRequest->id)->status->name,
        );
    }

    public function test_expiration_does_not_change_pending_payment_request_before_due_time(): void
    {
        $paymentRequest = $this->createPaymentRequest();

        $expirationAttempt = $this->repository->expirePending(
            $paymentRequest->id,
            CarbonImmutable::parse('2026-06-16 09:59:59'),
        );

        $this->assertNull($expirationAttempt);
        $this->assertSame(
            PaymentRequestStatus::Pending->value,
            PaymentRequest::query()->findOrFail($paymentRequest->id)->status->name,
        );
    }

    private function createPaymentRequest(): PaymentRequestRecord
    {
        $employee = User::query()->where('email', 'ana.silva@example.com')->firstOrFail();
        $currency = Currency::query()->where('code', 'BRL')->firstOrFail();
        $exchangeRateSource = ExchangeRateSource::query()->where('name', 'ExchangeRate-API')->firstOrFail();

        return $this->repository->create(new CreatePaymentRequestData(
            requesterId: $employee->id,
            currencyId: $currency->id,
            exchangeRateSourceId: $exchangeRateSource->id,
            title: 'Conference reimbursement',
            description: 'Hotel and local transportation costs.',
            amount: '123.45',
            eurExchangeRate: '5.88184850',
            amountEur: '20.99',
            exchangeRateFetchedAt: CarbonImmutable::parse('2026-06-14 10:00:00'),
            expiresAt: CarbonImmutable::parse('2026-06-16 10:00:00'),
        ));
    }
}
