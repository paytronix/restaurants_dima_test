<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePaymentRequest;
use App\Http\Requests\Payment\LegacyPayRequest;
use App\Http\Resources\PaymentTransactionResource;
use App\Models\Order;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {}

    public function store(CreatePaymentRequest $request, Order $order): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (empty($idempotencyKey)) {
            return response()->json([
                'title' => 'Bad Request',
                'detail' => 'Idempotency-Key header is required',
                'status' => 400,
            ], 400);
        }

        $result = $this->paymentService->createPayment(
            $order,
            $idempotencyKey,
            $request->input('provider'),
        );

        if ($result->isConflict) {
            return response()->json([
                'title' => 'Idempotency Conflict',
                'detail' => 'This idempotency key has already been used with different request parameters',
                'status' => 409,
            ], 409);
        }

        if ($result->isCached && $result->responseData !== null) {
            $responseData = $result->responseData;
            $statusCode = $responseData['_status_code'] ?? 200;
            unset($responseData['_status_code']);

            return response()->json($responseData, $statusCode);
        }

        if (! $result->success) {
            $statusCode = $result->errorCode === 'order_not_payable' ? 422 : 500;

            return response()->json([
                'title' => 'Payment Error',
                'detail' => $result->errorMessage,
                'status' => $statusCode,
                'errors' => [
                    'payment' => [$result->errorMessage],
                ],
            ], $statusCode);
        }

        return response()->json([
            'data' => new PaymentTransactionResource($result->transaction),
            'meta' => [],
        ], 201);
    }

    public function show(Request $request, Order $order, int $payment): JsonResponse
    {
        $transaction = $this->paymentService->getPayment($order, $payment);

        if ($transaction === null) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Payment not found',
                'status' => 404,
            ], 404);
        }

        return response()->json([
            'data' => new PaymentTransactionResource($transaction),
            'meta' => [],
        ]);
    }

    public function legacyPay(LegacyPayRequest $request, Order $order): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (empty($idempotencyKey)) {
            return response()->json([
                'title' => 'Bad Request',
                'detail' => 'Idempotency-Key header is required',
                'status' => 400,
            ], 400);
        }

        $result = $this->paymentService->processLegacyPay(
            $order,
            $idempotencyKey,
            $request->input('amount'),
        );

        if ($result->isConflict) {
            return response()->json([
                'title' => 'Idempotency Conflict',
                'detail' => 'This idempotency key has already been used with different request parameters',
                'status' => 409,
            ], 409);
        }

        if ($result->isCached && $result->responseData !== null) {
            $responseData = $result->responseData;
            $statusCode = $responseData['_status_code'] ?? 200;
            unset($responseData['_status_code']);

            $legacyResponse = [
                'data' => [
                    'id' => $responseData['data']['id'] ?? null,
                    'status' => $responseData['data']['status'] ?? 'succeeded',
                    'message' => 'Payment processed successfully',
                ],
                'meta' => [],
            ];

            return response()->json($legacyResponse, 200);
        }

        if (! $result->success) {
            $statusCode = $result->errorCode === 'order_not_payable' ? 422 : 500;

            return response()->json([
                'title' => 'Payment Error',
                'detail' => $result->errorMessage,
                'status' => $statusCode,
                'errors' => [
                    'payment' => [$result->errorMessage],
                ],
            ], $statusCode);
        }

        return response()->json([
            'data' => [
                'id' => $result->transaction?->id,
                'status' => $result->transaction?->status ?? 'succeeded',
                'message' => 'Payment processed successfully',
            ],
            'meta' => [],
        ]);
    }
}
