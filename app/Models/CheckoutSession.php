<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CheckoutSession extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'checkout_sessions';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'cart_id',
        'user_id',
        'shipping_address_id',
        'subtotal',
        'shipping_cost',
        'discount_total',
        'tax_total',
        'grand_total',
        'currency',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function shippingAddress()
    {
        return $this->belongsTo(ShippingAddress::class, 'shipping_address_id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'checkout_session_id', 'id');
    }
}
