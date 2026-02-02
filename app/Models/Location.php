<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Location extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const DEFAULT_TIMEZONE = 'Europe/Warsaw';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'phone',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'postal_code',
        'country',
        'lat',
        'lng',
        'timezone',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function pickupPoints(): HasMany
    {
        return $this->hasMany(PickupPoint::class);
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    public function leadTimeSetting(): HasOne
    {
        return $this->hasOne(LeadTimeSetting::class);
    }

    public function locationHours(): HasMany
    {
        return $this->hasMany(LocationHour::class);
    }

    public function locationExceptions(): HasMany
    {
        return $this->hasMany(LocationException::class);
    }

    public function fulfillmentWindows(): HasMany
    {
        return $this->hasMany(FulfillmentWindow::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
