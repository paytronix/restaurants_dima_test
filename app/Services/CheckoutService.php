<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        private CartService $cartService
    ) {
    }

    public function createOrder(Cart $cart, array $customerData): Order
    {
        if ($cart->items()->count() === 0) {
            throw new \InvalidArgumentException('Cart is empty');
        }

        $totals = $this->cartService->calculateTotal($cart);

        return DB::transaction(function () use ($cart, $customerData, $totals) {
            $order = Order::create([
                'user_id' => $cart->user_id,
                'order_number' => $this->generateOrderNumber(),
                'status' => OrderStatus::PENDING,
                'subtotal' => $totals['subtotal'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
                'customer_name' => $customerData['customer_name'],
                'customer_email' => $customerData['customer_email'],
                'customer_phone' => $customerData['customer_phone'],
                'pickup_time' => $customerData['pickup_time'] ?? null,
                'delivery_address' => $customerData['delivery_address'] ?? null,
                'special_instructions' => $customerData['special_instructions'] ?? null,
            ]);

            foreach ($cart->items as $cartItem) {
                $order->items()->create([
                    'menu_item_id' => $cartItem->menu_item_id,
                    'quantity' => $cartItem->quantity,
                    'price_snapshot' => $cartItem->price_snapshot,
                    'special_instructions' => $cartItem->special_instructions,
                ]);
            }

            $this->cartService->clearCart($cart);

            event(new OrderCreated($order));

            return $order;
        });
    }

    public function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . strtoupper(Str::random(8));
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    public function validateCheckoutData(array $data): void
    {
        $requiredFields = ['customer_name', 'customer_email', 'customer_phone'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field {$field} is required");
            }
        }

        if (!filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }
    }
}
