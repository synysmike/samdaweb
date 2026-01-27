<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class ProductImage extends Model
{
    use HasFactory, HasVersion7Uuids;
    protected $primaryKey = 'id';

    protected $table = 'product_images';

    protected $fillable = ['product_id', 'file_name', 'file_path', 'file_type', 'file_size'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
