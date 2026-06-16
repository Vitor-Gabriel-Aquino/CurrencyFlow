<?php

namespace App\Http\Controllers;

use App\Application\PaymentRequests\ApprovePaymentRequest;
use App\Application\PaymentRequests\CreatePaymentRequest;
use App\Application\PaymentRequests\ListPaymentRequests;
use App\Application\PaymentRequests\RejectPaymentRequest;
use App\Application\PaymentRequests\ShowPaymentRequest;
use App\Domain\ExchangeRates\Exceptions\ExchangeRateProviderException;
use App\Http\Requests\ListPaymentRequestsFormRequest;
use App\Http\Requests\ReviewPaymentRequestFormRequest;
use App\Http\Requests\StorePaymentRequestFormRequest;
use App\Http\Resources\PaymentRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentRequestController extends Controller
{
    public function index(ListPaymentRequestsFormRequest $request, ListPaymentRequests $listPaymentRequests): AnonymousResourceCollection
    {
        $paymentRequests = $listPaymentRequests->handle(
            $request->user(),
            $request->validated('status'),
            (int) $request->validated('page', 1),
            (int) $request->validated('per_page', 15),
        );

        return PaymentRequestResource::collection($paymentRequests->items)
            ->additional([
                'meta' => [
                    'current_page' => $paymentRequests->currentPage,
                    'per_page' => $paymentRequests->perPage,
                    'total' => $paymentRequests->total,
                    'last_page' => $paymentRequests->lastPage,
                ],
            ]);
    }

    public function store(StorePaymentRequestFormRequest $request, CreatePaymentRequest $createPaymentRequest): JsonResponse|PaymentRequestResource
    {
        try {
            $paymentRequest = $createPaymentRequest->handle($request->user(), $request->validated());
        } catch (ExchangeRateProviderException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }

        return (new PaymentRequestResource($paymentRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $paymentRequest, ShowPaymentRequest $showPaymentRequest): PaymentRequestResource
    {
        $record = $showPaymentRequest->handle($request->user(), $paymentRequest);

        abort_if(! $record, 404);

        return new PaymentRequestResource($record);
    }

    public function approve(
        ReviewPaymentRequestFormRequest $request,
        string $paymentRequest,
        ShowPaymentRequest $showPaymentRequest,
        ApprovePaymentRequest $approvePaymentRequest,
    ): JsonResponse|PaymentRequestResource {
        Gate::authorize('perform-finance-actions');

        abort_if(! $showPaymentRequest->handle($request->user(), $paymentRequest), 404);

        $record = $approvePaymentRequest->handle(
            $paymentRequest,
            $request->user(),
            $request->validated('review_note'),
        );

        if (! $record) {
            return response()->json([
                'message' => 'Only unexpired pending payment requests can be approved.',
            ], 409);
        }

        return new PaymentRequestResource($record);
    }

    public function reject(
        ReviewPaymentRequestFormRequest $request,
        string $paymentRequest,
        ShowPaymentRequest $showPaymentRequest,
        RejectPaymentRequest $rejectPaymentRequest,
    ): JsonResponse|PaymentRequestResource {
        Gate::authorize('perform-finance-actions');

        abort_if(! $showPaymentRequest->handle($request->user(), $paymentRequest), 404);

        $record = $rejectPaymentRequest->handle(
            $paymentRequest,
            $request->user(),
            $request->validated('review_note'),
        );

        if (! $record) {
            return response()->json([
                'message' => 'Only unexpired pending payment requests can be rejected.',
            ], 409);
        }

        return new PaymentRequestResource($record);
    }
}
