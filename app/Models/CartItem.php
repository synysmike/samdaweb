<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cart_items';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price',
        'currency',
        'title_snapshot',
        'sku_snapshot',
        'option_snapshot',
        'shop_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:0',
            'option_snapshot' => 'array',
        ];
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }
}
