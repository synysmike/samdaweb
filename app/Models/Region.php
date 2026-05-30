<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'regions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'translations',
        'flag',
        'wikiDataId',
    ];

    protected function casts(): array
    {
        return [
            'flag' => 'integer',
        ];
    }

    public function subregions()
    {
        return $this->hasMany(Subregion::class, 'region_id', 'id');
    }

    public function countries()
    {
        return $this->hasMany(Country::class, 'region_id', 'id');
    }
}
