<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierOption extends Model
{
    protected $fillable = [
        'modifier_id',
        'name',
        'price_delta',
        'is_active',
        'position',
    ];

    protected $casts = [
        'price_delta' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }
}
