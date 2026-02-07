<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttributeSet extends Model
{
    protected $table = 'product_attribute_sets';

    protected $primaryKey = ['product_id', 'product_attribute_id'];

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = ['product_id', 'product_attribute_id'];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        foreach ((array) $this->primaryKey as $key) {
            $query->where($key, $this->getAttribute($key));
        }

        return $query;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productAttribute()
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id', 'id');
    }
}
