<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modifier extends Model
{
    protected $fillable = [
        'name',
        'type',
        'min_select',
        'max_select',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class);
    }
}
