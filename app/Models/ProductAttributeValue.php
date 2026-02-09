<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class ProductAttributeValue extends Model
{
    use HasFactory, HasVersion7Uuids;
    protected $primaryKey = 'id';
    protected $table = 'product_attribute_values';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'product_attribute_id', 'value', 'code', 'is_active', 'sort_order'];

    public function attribute()
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id', 'id');
    }
}
