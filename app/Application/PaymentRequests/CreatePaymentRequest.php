<?php

namespace App\Application\PaymentRequests;

use App\Domain\ExchangeRates\Contracts\ExchangeRateProvider;
use App\Domain\PaymentRequests\Contracts\PaymentRequestRepository;
use App\Domain\PaymentRequests\Data\CreatePaymentRequestData;
use App\Domain\PaymentRequests\Data\PaymentRequestRecord;
use App\Domain\Shared\ValueObjects\CurrencyCode;
use App\Models\Currency;
use App\Models\ExchangeRateSource;
use App\Models\User;
use Carbon\CarbonImmutable;

class CreatePaymentRequest
{
    public function __construct(
        private readonly ExchangeRateProvider $exchangeRates,
        private readonly PaymentRequestRepository $paymentRequests,
    ) {
    }

    public function handle(User $requester, array $data): PaymentRequestRecord
    {
        $currencyCode = CurrencyCode::fromString($data['currency_code']);
        $currency = Currency::query()->where('code', $currencyCode->value)->firstOrFail();
        $quote = $this->exchangeRates->getEurExchangeRate($currencyCode);
        $exchangeRateSource = ExchangeRateSource::query()->where('name', $quote->source)->firstOrFail();
        $amount = bcadd((string) $data['amount'], '0', 4);

        return $this->paymentRequests->create(new CreatePaymentRequestData(
            requesterId: $requester->id,
            currencyId: $currency->id,
            exchangeRateSourceId: $exchangeRateSource->id,
            title: $data['title'],
            description: $data['description'] ?? null,
            amount: $amount,
            eurExchangeRate: $quote->eurExchangeRate,
            amountEur: $this->divideAndRound($amount, $quote->eurExchangeRate, 4),
            exchangeRateFetchedAt: $quote->fetchedAt,
            expiresAt: CarbonImmutable::now('UTC')->addHours(48),
        ));
    }

    private function divideAndRound(string $amount, string $divisor, int $scale): string
    {
        $extraScale = $scale + 1;
        $value = bcdiv($amount, $divisor, $extraScale);

        return bcadd($value, '0.'.str_repeat('0', $scale).'5', $scale);
    }
}
