<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getOrCreateCart(?User $user, ?string $sessionId): Cart
    {
        if ($user !== null) {
            $cart = Cart::where('user_id', $user->id)->first();
        } elseif ($sessionId !== null) {
            $cart = Cart::where('session_id', $sessionId)->first();
        } else {
            $cart = null;
        }

        if ($cart === null) {
            $cart = Cart::create([
                'user_id' => $user?->id,
                'session_id' => $sessionId,
            ]);
        }

        return $cart;
    }

    public function addItem(Cart $cart, int $menuItemId, int $quantity, ?string $instructions): CartItem
    {
        $menuItem = MenuItem::findOrFail($menuItemId);

        if (!$menuItem->available) {
            throw new \InvalidArgumentException('Menu item is not available');
        }

        if ($quantity < 1 || $quantity > 99) {
            throw new \InvalidArgumentException('Quantity must be between 1 and 99');
        }

        $existingItem = CartItem::where('cart_id', $cart->id)
            ->where('menu_item_id', $menuItemId)
            ->first();

        if ($existingItem !== null) {
            $existingItem->quantity += $quantity;
            $existingItem->special_instructions = $instructions;
            $existingItem->save();
            return $existingItem;
        }

        return CartItem::create([
            'cart_id' => $cart->id,
            'menu_item_id' => $menuItemId,
            'quantity' => $quantity,
            'price_snapshot' => $menuItem->price,
            'special_instructions' => $instructions,
        ]);
    }

    public function updateItem(CartItem $item, array $data): CartItem
    {
        if (isset($data['quantity'])) {
            if ($data['quantity'] < 1 || $data['quantity'] > 99) {
                throw new \InvalidArgumentException('Quantity must be between 1 and 99');
            }
            $item->quantity = $data['quantity'];
        }

        if (isset($data['special_instructions'])) {
            $item->special_instructions = $data['special_instructions'];
        }

        $item->save();
        return $item;
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function calculateTotal(Cart $cart): array
    {
        $cart->load('items');

        $subtotal = $cart->items->reduce(function ($carry, $item) {
            return $carry + ($item->price_snapshot * $item->quantity);
        }, 0);

        $taxRate = (float) config('orders.tax_rate', 0.08);
        $tax = $subtotal * $taxRate;
        $total = $subtotal + $tax;

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
        ];
    }

    public function clearCart(Cart $cart): void
    {
        DB::transaction(function () use ($cart) {
            $cart->items()->delete();
            $cart->forceDelete();
        });
    }
}
