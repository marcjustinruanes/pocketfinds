<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    public $timestamps = false;
    protected $fillable = ['province_id', 'name'];
}
