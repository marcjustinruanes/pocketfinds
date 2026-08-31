<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsCenter extends Model
{
    protected $fillable = ['name', 'address', 'contact_no', 'hours_note', 'updated_by'];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
