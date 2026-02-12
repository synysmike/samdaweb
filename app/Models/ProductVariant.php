<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory, HasVersion7Uuids;
    
    protected $table = 'product_variants';
    protected $primaryKey = 'id';
    protected $autoincrement = false;
    protected $fillable = ['id', 'product_id', 'sku', 'price', 'stock', 'option_signature'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
