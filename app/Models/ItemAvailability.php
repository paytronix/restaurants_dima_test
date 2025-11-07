<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAvailability extends Model
{
    protected $fillable = [
        'menu_item_id',
        'day_of_week',
        'time_from',
        'time_to',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
