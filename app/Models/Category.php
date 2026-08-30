<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug'];

    public function sellers()
    {
        return $this->belongsToMany(User::class, 'category_seller');
    }
}
