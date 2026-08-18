<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    protected $fillable = ['title', 'content', 'slug', 'updated_by'];

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
