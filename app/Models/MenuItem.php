<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'currency',
        'image_url',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function modifiers(): BelongsToMany
    {
        return $this->belongsToMany(Modifier::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(ItemAvailability::class);
    }

    public function soldouts(): HasMany
    {
        return $this->hasMany(ItemSoldout::class);
    }
}
