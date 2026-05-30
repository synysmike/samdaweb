<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory, HasVersion7Uuids;

    protected $table = 'carts';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'currency',
        'status',
        'notes',
        'guest_token',
        'expires_at',
        'checked_out_at',
    ];

    protected function casts(): array
    {
        return [
            'guest_token' => 'string',
            'expires_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(CartItem::class, 'cart_id', 'id');
    }

    public function checkoutSessions()
    {
        return $this->hasMany(CheckoutSession::class, 'cart_id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'cart_id', 'id');
    }
}
