<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    public function getOrder(int $orderId, ?User $user): Order
    {
        $query = Order::with(['items.menuItem', 'paymentAttempts']);

        if ($user !== null) {
            $query->where('user_id', $user->id);
        }

        $order = $query->findOrFail($orderId);

        return $order;
    }

    public function getUserOrders(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Order::where('user_id', $user->id)
            ->with(['items.menuItem']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = 'desc';

        if (str_starts_with($sortField, '-')) {
            $sortField = substr($sortField, 1);
            $sortDirection = 'asc';
        }

        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->paginate($perPage);
    }

    public function updateStatus(Order $order, OrderStatus $newStatus): Order
    {
        $oldStatus = $order->status;

        if (!$oldStatus->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$oldStatus->value} to {$newStatus->value}"
            );
        }

        $order->status = $newStatus;
        $order->save();

        event(new OrderStatusChanged($order, $oldStatus, $newStatus));

        return $order;
    }

    public function validateStatusTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return $from->canTransitionTo($to);
    }
}
