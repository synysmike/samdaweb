<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'orders';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_number',
        'cart_id',
        'checkout_session_id',
        'user_id',
        'shop_id',
        'shipping_address_id',
        'subtotal',
        'shipping_cost',
        'discount_total',
        'tax_total',
        'grand_total',
        'currency',
        'order_status',
        'payment_status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_snapshot',
        'notes',
        'paid_at',
        'cancelled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'shipping_snapshot' => 'array',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'id');
    }

    public function checkoutSession()
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

    public function shippingAddress()
    {
        return $this->belongsTo(ShippingAddress::class, 'shipping_address_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id', 'id');
    }

    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class, 'order_id', 'id');
    }
}
