<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationException extends Model
{
    public const TYPE_CLOSED_ALL_DAY = 'closed_all_day';

    public const TYPE_OPEN_CUSTOM = 'open_custom';

    public const TYPE_BLACKOUT_WINDOW = 'blackout_window';

    public const VALID_TYPES = [
        self::TYPE_CLOSED_ALL_DAY,
        self::TYPE_OPEN_CUSTOM,
        self::TYPE_BLACKOUT_WINDOW,
    ];

    public const FULFILLMENT_PICKUP = 'pickup';

    public const FULFILLMENT_DELIVERY = 'delivery';

    public const FULFILLMENT_BOTH = 'both';

    public const VALID_FULFILLMENT_TYPES = [
        self::FULFILLMENT_PICKUP,
        self::FULFILLMENT_DELIVERY,
        self::FULFILLMENT_BOTH,
    ];

    protected $fillable = [
        'location_id',
        'date',
        'type',
        'open_time',
        'close_time',
        'fulfillment_type',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isClosedAllDay(): bool
    {
        return $this->type === self::TYPE_CLOSED_ALL_DAY;
    }

    public function isOpenCustom(): bool
    {
        return $this->type === self::TYPE_OPEN_CUSTOM;
    }

    public function isBlackoutWindow(): bool
    {
        return $this->type === self::TYPE_BLACKOUT_WINDOW;
    }

    public function appliesToFulfillmentType(?string $type): bool
    {
        if ($this->fulfillment_type === null || $this->fulfillment_type === self::FULFILLMENT_BOTH) {
            return true;
        }

        if ($type === null) {
            return true;
        }

        return $this->fulfillment_type === $type;
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForDateRange($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function scopeForFulfillmentType($query, ?string $type)
    {
        if ($type === null) {
            return $query;
        }

        return $query->where(function ($q) use ($type) {
            $q->whereNull('fulfillment_type')
                ->orWhere('fulfillment_type', $type)
                ->orWhere('fulfillment_type', self::FULFILLMENT_BOTH);
        });
    }

    public function scopeClosedAllDay($query)
    {
        return $query->where('type', self::TYPE_CLOSED_ALL_DAY);
    }

    public function scopeOpenCustom($query)
    {
        return $query->where('type', self::TYPE_OPEN_CUSTOM);
    }

    public function scopeBlackoutWindows($query)
    {
        return $query->where('type', self::TYPE_BLACKOUT_WINDOW);
    }
}
