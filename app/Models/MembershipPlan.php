<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MembershipPlan extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'membership_plans';
    protected $primaryKey = 'uuid';
    protected $fillable = [
        'name',
        'description',
        'price',
    ];
}
