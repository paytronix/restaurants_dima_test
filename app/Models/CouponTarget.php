<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponTarget extends Model
{
    public const TYPE_CATEGORY = 'category';

    public const TYPE_MENU_ITEM = 'menu_item';

    protected $fillable = [
        'coupon_id',
        'target_type',
        'target_id',
    ];

    protected $casts = [
        'target_id' => 'integer',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isCategory(): bool
    {
        return $this->target_type === self::TYPE_CATEGORY;
    }

    public function isMenuItem(): bool
    {
        return $this->target_type === self::TYPE_MENU_ITEM;
    }

    public function scopeCategories($query)
    {
        return $query->where('target_type', self::TYPE_CATEGORY);
    }

    public function scopeMenuItems($query)
    {
        return $query->where('target_type', self::TYPE_MENU_ITEM);
    }
}
