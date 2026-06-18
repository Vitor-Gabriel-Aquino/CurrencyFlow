<?php

namespace App\Http\Controllers;

use App\Domain\ExchangeRates\Contracts\ExchangeRateProvider;
use App\Domain\ExchangeRates\Exceptions\ExchangeRateProviderException;
use App\Domain\Shared\ValueObjects\CurrencyCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRatePreviewController extends Controller
{
    public function show(Request $request, ExchangeRateProvider $exchangeRates): JsonResponse
    {
        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        try {
            $quote = $exchangeRates->getEurExchangeRate(
                CurrencyCode::fromString($validated['currency_code']),
            );
        } catch (ExchangeRateProviderException) {
            return response()->json([
                'message' => 'Exchange rates are temporarily unavailable.',
            ], 503);
        }

        return response()->json([
            'data' => [
                'base_currency_code' => $quote->baseCurrencyCode,
                'local_currency_code' => $quote->localCurrencyCode,
                'eur_exchange_rate' => $quote->eurExchangeRate,
                'source' => $quote->source,
                'fetched_at' => $quote->fetchedAt->toJSON(),
            ],
        ]);
    }
}
