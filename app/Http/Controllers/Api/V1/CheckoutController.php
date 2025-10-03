<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CheckoutService $checkoutService
    ) {
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        $user = auth()->user();
        $sessionId = session()->getId();

        try {
            $cart = $this->cartService->getOrCreateCart($user, $sessionId);

            if ($cart->items()->count() === 0) {
                return response()->json([
                    'title' => 'Validation Error',
                    'detail' => 'Cart is empty',
                    'status' => 422,
                    'trace_id' => (string) Str::uuid(),
                ], 422);
            }

            $order = $this->checkoutService->createOrder($cart, $request->validated());

            $order->load(['items.menuItem']);

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
                        'created_at' => $order->created_at->toIso8601String(),
                    ],
                ],
                'meta' => [
                    'trace_id' => (string) Str::uuid(),
                ],
            ], 201);
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
