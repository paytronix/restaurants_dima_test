<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService
    ) {
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $sessionId = session()->getId();

        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $cart->load(['items.menuItem']);

        $totals = $this->cartService->calculateTotal($cart);

        return response()->json([
            'data' => [
                'cart' => [
                    'id' => $cart->id,
                    'items' => $cart->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'menu_item' => [
                                'id' => $item->menuItem->id,
                                'name' => $item->menuItem->name,
                                'price' => $item->menuItem->price,
                            ],
                            'quantity' => $item->quantity,
                            'price_snapshot' => $item->price_snapshot,
                            'special_instructions' => $item->special_instructions,
                            'subtotal' => (float) $item->price_snapshot * $item->quantity,
                        ];
                    }),
                    'totals' => $totals,
                ],
            ],
            'meta' => [
                'trace_id' => (string) Str::uuid(),
            ],
        ]);
    }

    public function store(AddCartItemRequest $request): JsonResponse
    {
        $user = auth()->user();
        $sessionId = session()->getId();

        try {
            $cart = $this->cartService->getOrCreateCart($user, $sessionId);

            $cartItem = $this->cartService->addItem(
                $cart,
                $request->input('menu_item_id'),
                $request->input('quantity'),
                $request->input('special_instructions')
            );

            $cartItem->load('menuItem');

            return response()->json([
                'data' => [
                    'cart_item' => [
                        'id' => $cartItem->id,
                        'menu_item' => [
                            'id' => $cartItem->menuItem->id,
                            'name' => $cartItem->menuItem->name,
                            'price' => $cartItem->menuItem->price,
                        ],
                        'quantity' => $cartItem->quantity,
                        'price_snapshot' => $cartItem->price_snapshot,
                        'special_instructions' => $cartItem->special_instructions,
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

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        try {
            $updatedItem = $this->cartService->updateItem($cartItem, $request->validated());
            $updatedItem->load('menuItem');

            return response()->json([
                'data' => [
                    'cart_item' => [
                        'id' => $updatedItem->id,
                        'menu_item' => [
                            'id' => $updatedItem->menuItem->id,
                            'name' => $updatedItem->menuItem->name,
                            'price' => $updatedItem->menuItem->price,
                        ],
                        'quantity' => $updatedItem->quantity,
                        'price_snapshot' => $updatedItem->price_snapshot,
                        'special_instructions' => $updatedItem->special_instructions,
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

    public function destroy(CartItem $cartItem): JsonResponse
    {
        $this->cartService->removeItem($cartItem);

        return response()->json([
            'data' => [
                'message' => 'Cart item removed successfully',
            ],
            'meta' => [
                'trace_id' => (string) Str::uuid(),
            ],
        ]);
    }
}
