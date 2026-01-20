<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ShippingAddress extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';

    protected $table = 'shipping_addresses';

    protected $fillable = ['user_id', 'address_type', 'address_title', 'first_name', 'last_name', 'email', 'phone_number', 'countrry_id', 'country_name', 'state_id', 'state_name', 'city_id', 'city_name', 'zip_code', 'address_description'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
