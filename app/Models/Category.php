<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'slug'];

    protected $hidden = ['image_data', 'image_mime', 'image_sha256', 'image_source'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
