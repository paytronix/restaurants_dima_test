<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryZone extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'location_id',
        'name',
        'status',
        'polygon_geojson',
        'priority',
    ];

    protected $casts = [
        'polygon_geojson' => 'array',
        'priority' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function pricingRule(): HasOne
    {
        return $this->hasOne(DeliveryPricingRule::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getPolygonCoordinates(): array
    {
        $geojson = $this->polygon_geojson;

        if (! is_array($geojson)) {
            return [];
        }

        if (isset($geojson['coordinates'][0]) && is_array($geojson['coordinates'][0])) {
            return $geojson['coordinates'][0];
        }

        return [];
    }
}
