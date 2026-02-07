<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class ProductAttribute extends Model
{
    use HasFactory, HasVersion7Uuids;
    protected $primaryKey = 'id';
    protected $table = 'product_attributes';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'shop_id', 'name', 'code', 'type', 'is_active'];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

    public function attributeSets()
    {
        return $this->hasMany(ProductAttributeSet::class, 'product_attribute_id', 'id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_attribute_sets', 'product_attribute_id', 'product_id');
    }
}
