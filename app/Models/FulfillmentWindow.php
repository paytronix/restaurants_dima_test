<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentWindow extends Model
{
    public const FULFILLMENT_PICKUP = 'pickup';

    public const FULFILLMENT_DELIVERY = 'delivery';

    public const VALID_FULFILLMENT_TYPES = [
        self::FULFILLMENT_PICKUP,
        self::FULFILLMENT_DELIVERY,
    ];

    public const DEFAULT_SLOT_INTERVAL_MIN = 15;

    public const DEFAULT_SLOT_DURATION_MIN = 15;

    public const DEFAULT_MIN_LEAD_TIME_MIN = 30;

    public const DEFAULT_CUTOFF_MIN_BEFORE_CLOSE = 30;

    public const DEFAULT_MAX_DAYS_AHEAD = 7;

    protected $fillable = [
        'location_id',
        'fulfillment_type',
        'slot_interval_min',
        'slot_duration_min',
        'min_lead_time_min',
        'cutoff_min_before_close',
        'max_days_ahead',
    ];

    protected $casts = [
        'slot_interval_min' => 'integer',
        'slot_duration_min' => 'integer',
        'min_lead_time_min' => 'integer',
        'cutoff_min_before_close' => 'integer',
        'max_days_ahead' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function scopeForFulfillmentType($query, string $type)
    {
        return $query->where('fulfillment_type', $type);
    }

    public static function getDefaultSettings(string $fulfillmentType): array
    {
        return [
            'fulfillment_type' => $fulfillmentType,
            'slot_interval_min' => self::DEFAULT_SLOT_INTERVAL_MIN,
            'slot_duration_min' => self::DEFAULT_SLOT_DURATION_MIN,
            'min_lead_time_min' => self::DEFAULT_MIN_LEAD_TIME_MIN,
            'cutoff_min_before_close' => self::DEFAULT_CUTOFF_MIN_BEFORE_CLOSE,
            'max_days_ahead' => self::DEFAULT_MAX_DAYS_AHEAD,
        ];
    }
}
