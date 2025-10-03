<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case IN_PREP = 'in_prep';
    case READY = 'ready';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::PAID, self::CANCELLED, self::FAILED]),
            self::PAID => in_array($newStatus, [self::IN_PREP, self::CANCELLED]),
            self::IN_PREP => in_array($newStatus, [self::READY, self::CANCELLED]),
            self::READY => in_array($newStatus, [self::COMPLETED, self::CANCELLED]),
            self::COMPLETED => false,
            self::CANCELLED => false,
            self::FAILED => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::IN_PREP => 'In Preparation',
            self::READY => 'Ready',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::FAILED => 'Failed',
        };
    }
}
