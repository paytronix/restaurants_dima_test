<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'menu_item_id',
        'quantity',
        'price_snapshot',
        'special_instructions',
    ];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
