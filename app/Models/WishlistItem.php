<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WishlistItem extends Model
{
    use HasUuids, HasFactory;
    protected $primaryKey = 'id';
    protected $fillable = ['id', 'wishlist_id', 'product_id'];
    public function wishlist()
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
