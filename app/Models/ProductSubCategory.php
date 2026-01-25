<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class ProductSubCategory extends Model
{
    use HasVersion7Uuids;
    protected $primaryKey = 'id';

    protected $table = 'product_sub_categories';

    protected $fillable = ['category_id', 'name', 'is_active'];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id', 'id');
    }
}
