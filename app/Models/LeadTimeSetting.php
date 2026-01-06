<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadTimeSetting extends Model
{
    protected $fillable = [
        'location_id',
        'pickup_lead_time_min',
        'delivery_lead_time_min',
        'zone_extra_time_min',
    ];

    protected $casts = [
        'pickup_lead_time_min' => 'integer',
        'delivery_lead_time_min' => 'integer',
        'zone_extra_time_min' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
