<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shop extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'shops';
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = ['id', 'name', 'phone', 'country_id', 'country_name', 'state_id', 'state_name', 'city_id', 'city_name', 'zip_code', 'description', 'membership_plans_id', 'valid_verification', 'valid_by'];

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }

    public function membership_plans()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plans_id', 'id');
    }
   
}
