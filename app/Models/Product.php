<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class Product extends Model
{
    use HasFactory, HasVersion7Uuids;
    protected $primaryKey = 'id';
    protected $table = 'products';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'shop_id', 'title', 'slug', 'description', 'category_id', 'is_active', 'is_visible', 'country_id', 'country_name', 'state_id', 'state_name', 'city_id', 'city_name', 'min_price', 'max_price'];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function attributeSets()
    {
        return $this->hasMany(ProductAttributeSet::class);
    }

    public function productAttributes()
    {
        return $this->belongsToMany(ProductAttribute::class, 'product_attribute_sets', 'product_id', 'product_attribute_id');
    }
}
