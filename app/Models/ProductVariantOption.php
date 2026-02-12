<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class ProductVariantOption extends Model
{
    use HasFactory, HasVersion7Uuids;

    protected $table = 'product_variant_options';
    protected $primaryKey = 'id';
    protected $autoincrement = false;
    protected $fillable = ['id', 'product_variant_id', 'product_attribute_id', 'product_attribute_value_id', 'created_at', 'updated_at'];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function productAttribute()
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id', 'id');
    }

    public function productAttributeValue()
    {
        return $this->belongsTo(ProductAttributeValue::class, 'product_attribute_value_id', 'id');
    }
}
