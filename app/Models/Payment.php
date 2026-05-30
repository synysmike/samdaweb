<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payments';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_id',
        'payment_number',
        'payment_method',
        'payment_channel',
        'provider',
        'provider_reference',
        'amount',
        'fee_amount',
        'net_amount',
        'currency',
        'status',
        'payment_url',
        'va_number',
        'qr_string',
        'expired_at',
        'paid_at',
        'failed_at',
        'cancelled_at',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'fee_amount' => 'decimal:0',
            'net_amount' => 'decimal:0',
            'raw_response' => 'array',
            'expired_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(PaymentLog::class, 'payment_id', 'id');
    }
}
