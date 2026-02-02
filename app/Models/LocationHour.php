<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationHour extends Model
{
    public const FULFILLMENT_PICKUP = 'pickup';

    public const FULFILLMENT_DELIVERY = 'delivery';

    public const FULFILLMENT_BOTH = 'both';

    public const VALID_FULFILLMENT_TYPES = [
        self::FULFILLMENT_PICKUP,
        self::FULFILLMENT_DELIVERY,
        self::FULFILLMENT_BOTH,
    ];

    public const DAY_SUNDAY = 0;

    public const DAY_MONDAY = 1;

    public const DAY_TUESDAY = 2;

    public const DAY_WEDNESDAY = 3;

    public const DAY_THURSDAY = 4;

    public const DAY_FRIDAY = 5;

    public const DAY_SATURDAY = 6;

    public const DAY_NAMES = [
        self::DAY_SUNDAY => 'Sunday',
        self::DAY_MONDAY => 'Monday',
        self::DAY_TUESDAY => 'Tuesday',
        self::DAY_WEDNESDAY => 'Wednesday',
        self::DAY_THURSDAY => 'Thursday',
        self::DAY_FRIDAY => 'Friday',
        self::DAY_SATURDAY => 'Saturday',
    ];

    protected $fillable = [
        'location_id',
        'day_of_week',
        'open_time',
        'close_time',
        'fulfillment_type',
        'is_closed',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function getDayNameAttribute(): string
    {
        return self::DAY_NAMES[$this->day_of_week] ?? 'Unknown';
    }

    public function appliesToFulfillmentType(string $type): bool
    {
        if ($this->fulfillment_type === self::FULFILLMENT_BOTH) {
            return true;
        }

        return $this->fulfillment_type === $type;
    }

    public function scopeForDay($query, int $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    public function scopeForFulfillmentType($query, string $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->where('fulfillment_type', $type)
                ->orWhere('fulfillment_type', self::FULFILLMENT_BOTH);
        });
    }

    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }
}
