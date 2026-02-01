<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class ProductCategory extends Model
{
    use HasVersion7Uuids;
    protected $primaryKey = 'id';

    protected $table = 'product_categories';

    protected $fillable = ['name', 'is_active', 'slug'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    
    public function productSubCategories()
    {
        return $this->hasMany(ProductSubCategory::class);
    }
}
