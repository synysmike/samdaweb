<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class ProductCategory extends Model
{
    use HasVersion7Uuids;
    protected $primaryKey = 'id';

    protected $table = 'product_categories';

    protected $fillable = ['id', 'parent_id', 'name', 'slug', 'created_at', 'updated_at'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    
    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(ProductCategory::class, 'parent_id', 'id');
    }
}
