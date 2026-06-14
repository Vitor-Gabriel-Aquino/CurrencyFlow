<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Country::query()
                ->orderBy('name')
                ->get(['code', 'name'])
                ->map(fn (Country $country): array => [
                    'code' => $country->code,
                    'name' => $country->name,
                ]),
        ]);
    }
}
