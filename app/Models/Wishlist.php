<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wishlist extends Model
{    
    use HasUuids, HasFactory;
    protected $primaryKey = 'id';
    protected $fillable = ['id', 'owner_type', 'user_id', 'guest_token', 'status', 'last_seen_at'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }
}
