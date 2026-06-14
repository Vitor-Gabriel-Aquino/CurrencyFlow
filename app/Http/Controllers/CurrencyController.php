<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Currency::query()
                ->orderBy('code')
                ->get(['code', 'name', 'exponent'])
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'exponent' => $currency->exponent,
                ]),
        ]);
    }
}
