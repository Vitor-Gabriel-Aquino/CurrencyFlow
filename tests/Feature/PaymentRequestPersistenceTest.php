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
use App\Models\PaymentRequestEvent;
use App\Models\PaymentRequestStatus as PaymentRequestStatusModel;
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
            'from_status_id' => null,
            'to_status_id' => $this->statusId(PaymentRequestStatus::Pending),
            'note' => null,
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

    public function test_pending_payment_request_can_be_approved(): void
    {
        $paymentRequest = $this->createPaymentRequest();
        $finance = $this->finance();

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

        $this->assertPaymentRequestEventTransition(
            $paymentRequest->id,
            PaymentRequestEventType::Approved,
            PaymentRequestStatus::Pending,
            PaymentRequestStatus::Approved,
            'Approved for reimbursement.',
        );
    }

    public function test_approved_payment_request_cannot_be_rejected(): void
    {
        $paymentRequest = $this->createPaymentRequest();
        $finance = $this->finance();

        $this->repository->approvePending(
            $paymentRequest->id,
            new ReviewPaymentRequestData(
                reviewerId: $finance->id,
                reviewNote: 'Approved for reimbursement.',
                reviewedAt: CarbonImmutable::parse('2026-06-14 11:00:00'),
            ),
        );

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

    public function test_pending_payment_request_can_expire_after_due_time(): void
    {
        $paymentRequest = $this->createPaymentRequest();

        $expiredPaymentRequest = $this->repository->expirePending(
            $paymentRequest->id,
            CarbonImmutable::parse('2026-06-16 10:00:01'),
        );

        $this->assertNotNull($expiredPaymentRequest);
        $this->assertSame(PaymentRequestStatus::Expired->value, $expiredPaymentRequest->status);

        $this->assertPaymentRequestEventTransition(
            $paymentRequest->id,
            PaymentRequestEventType::Expired,
            PaymentRequestStatus::Pending,
            PaymentRequestStatus::Expired,
            'Expired automatically by system',
        );
    }

    public function test_expired_payment_request_cannot_expire_again(): void
    {
        $paymentRequest = $this->createPaymentRequest();
        $this->repository->expirePending(
            $paymentRequest->id,
            CarbonImmutable::parse('2026-06-16 10:00:01'),
        );

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

    private function finance(): User
    {
        return User::query()->where('email', 'marta.kowalska@example.com')->firstOrFail();
    }

    private function assertPaymentRequestEventTransition(
        string $paymentRequestId,
        PaymentRequestEventType $eventType,
        PaymentRequestStatus $fromStatus,
        PaymentRequestStatus $toStatus,
        ?string $note,
    ): void {
        $event = PaymentRequestEvent::query()
            ->where('payment_request_id', $paymentRequestId)
            ->where('from_status_id', $this->statusId($fromStatus))
            ->where('to_status_id', $this->statusId($toStatus))
            ->where('note', $note)
            ->whereHas('eventType', fn ($query) => $query->where('name', $eventType->value))
            ->first();

        $this->assertNotNull($event);
    }

    private function statusId(PaymentRequestStatus $status): string
    {
        return PaymentRequestStatusModel::query()
            ->where('name', $status->value)
            ->firstOrFail()
            ->id;
    }
}
