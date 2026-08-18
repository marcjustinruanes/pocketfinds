<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['user_id', 'title', 'message', 'notification_type', 'reference_id', 'is_read'];
    protected $casts    = ['created_at' => 'datetime', 'is_read' => 'boolean'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
