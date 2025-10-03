<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessPaymentRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentServiceStub;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private PaymentServiceStub $paymentService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return response()->json([
                'title' => 'Unauthorized',
                'detail' => 'Authentication required',
                'status' => 401,
                'trace_id' => (string) Str::uuid(),
            ], 401);
        }

        $filters = [
            'status' => $request->input('status'),
            'sort' => $request->input('sort'),
            'per_page' => $request->input('per_page', 15),
        ];

        $orders = $this->orderService->getUserOrders($user, $filters);

        return response()->json([
            'data' => [
                'orders' => $orders->items(),
            ],
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
                'trace_id' => (string) Str::uuid(),
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $user = auth()->user();

        try {
            $order = $this->orderService->getOrder($order->id, $user);
            
            return response()->json([
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status->value,
                        'subtotal' => $order->subtotal,
                        'tax' => $order->tax,
                        'total' => $order->total,
                        'customer_name' => $order->customer_name,
                        'customer_email' => $order->customer_email,
                        'customer_phone' => $order->customer_phone,
                        'pickup_time' => $order->pickup_time?->toIso8601String(),
                        'delivery_address' => $order->delivery_address,
                        'special_instructions' => $order->special_instructions,
                        'items' => $order->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'menu_item' => [
                                    'id' => $item->menuItem->id,
                                    'name' => $item->menuItem->name,
                                ],
                                'quantity' => $item->quantity,
                                'price_snapshot' => $item->price_snapshot,
                                'special_instructions' => $item->special_instructions,
                            ];
                        }),
                        'payment_attempts' => $order->paymentAttempts->map(function ($attempt) {
                            return [
                                'id' => $attempt->id,
                                'amount' => $attempt->amount,
                                'status' => $attempt->status,
                                'created_at' => $attempt->created_at->toIso8601String(),
                            ];
                        }),
                        'created_at' => $order->created_at->toIso8601String(),
                        'updated_at' => $order->updated_at->toIso8601String(),
                    ],
                ],
                'meta' => [
                    'trace_id' => (string) Str::uuid(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Order not found',
                'status' => 404,
                'trace_id' => (string) Str::uuid(),
            ], 404);
        }
    }

    public function processPayment(ProcessPaymentRequest $request, Order $order): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey === null) {
            return response()->json([
                'title' => 'Validation Error',
                'detail' => 'Idempotency-Key header is required',
                'status' => 422,
                'trace_id' => (string) Str::uuid(),
            ], 422);
        }

        try {
            $existingAttempt = $this->paymentService->checkIdempotency($idempotencyKey);
            
            if ($existingAttempt !== null) {
                return response()->json([
                    'data' => [
                        'payment_attempt' => [
                            'id' => $existingAttempt->id,
                            'order_id' => $existingAttempt->order_id,
                            'amount' => $existingAttempt->amount,
                            'status' => $existingAttempt->status,
                            'provider_reference' => $existingAttempt->provider_reference,
                            'created_at' => $existingAttempt->created_at->toIso8601String(),
                        ],
                    ],
                    'meta' => [
                        'idempotent' => true,
                        'trace_id' => (string) Str::uuid(),
                    ],
                ]);
            }

            $paymentAttempt = $this->paymentService->processPayment(
                $order,
                $idempotencyKey,
                $request->validated()
            );

            $statusCode = $paymentAttempt->status === 'success' ? 200 : 402;

            return response()->json([
                'data' => [
                    'payment_attempt' => [
                        'id' => $paymentAttempt->id,
                        'order_id' => $paymentAttempt->order_id,
                        'amount' => $paymentAttempt->amount,
                        'status' => $paymentAttempt->status,
                        'provider_reference' => $paymentAttempt->provider_reference,
                        'error_message' => $paymentAttempt->error_message,
                        'created_at' => $paymentAttempt->created_at->toIso8601String(),
                    ],
                ],
                'meta' => [
                    'trace_id' => (string) Str::uuid(),
                ],
            ], $statusCode);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'title' => 'Validation Error',
                'detail' => $e->getMessage(),
                'status' => 422,
                'trace_id' => (string) Str::uuid(),
            ], 422);
        }
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        try {
            $newStatus = OrderStatus::from($request->input('status'));
            
            $updatedOrder = $this->orderService->updateStatus($order, $newStatus);

            return response()->json([
                'data' => [
                    'order' => [
                        'id' => $updatedOrder->id,
                        'order_number' => $updatedOrder->order_number,
                        'status' => $updatedOrder->status->value,
                        'updated_at' => $updatedOrder->updated_at->toIso8601String(),
                    ],
                ],
                'meta' => [
                    'trace_id' => (string) Str::uuid(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'title' => 'Validation Error',
                'detail' => $e->getMessage(),
                'status' => 422,
                'trace_id' => (string) Str::uuid(),
            ], 422);
        }
    }
}
