<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class ProductSubCategory extends Model
{
    use HasVersion7Uuids;
    protected $primaryKey = 'id';

    protected $table = 'product_sub_categories';

    protected $fillable = ['product_category_id', 'name', 'is_active', 'slug'];

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id', 'id');
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
