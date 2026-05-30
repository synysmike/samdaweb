<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';
    protected $primaryKey = 'id';

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function subregion()
    {
        return $this->belongsTo(Subregion::class, 'subregion_id', 'id');
    }

    public function states()
    {
        return $this->hasMany(State::class);
    }
}
