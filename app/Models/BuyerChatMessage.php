<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuyerChatMessage extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['content' => 'encrypted', 'payload' => 'encrypted:array'];
}
