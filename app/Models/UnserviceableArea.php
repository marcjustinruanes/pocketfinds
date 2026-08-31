<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnserviceableArea extends Model
{
    protected $fillable = ['municipality', 'note', 'added_by'];

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
