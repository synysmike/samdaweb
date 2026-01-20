<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{    
    protected $fillable = ['id', 'slug', 'phone_number', 'tax_id_number', 'profile_picture', 'cover_image', 'cover_image_type', 'notify_on_message', 'show_email', 'show_phone_number'];

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
