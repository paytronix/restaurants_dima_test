<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPricingRule extends Model
{
    protected $fillable = [
        'delivery_zone_id',
        'fee_amount',
        'min_order_amount',
        'free_delivery_threshold',
        'currency',
    ];

    protected $casts = [
        'fee_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'free_delivery_threshold' => 'decimal:2',
    ];

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }
}
