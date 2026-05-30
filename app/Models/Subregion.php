<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subregion extends Model
{
    protected $table = 'subregions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'translations',
        'region_id',
        'flag',
        'wikiDataId',
    ];

    protected function casts(): array
    {
        return [
            'flag' => 'integer',
        ];
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function countries()
    {
        return $this->hasMany(Country::class, 'subregion_id', 'id');
    }
}
