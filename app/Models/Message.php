<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'sender_id', 'receiver_id', 'body', 'read',
        'product_id', 'reply_to_id', 'reactions',
        'attachment_path', 'attachment_name', 'attachment_type',
        'attachment_mime', 'attachment_size',
    ];

    protected $casts = ['read' => 'boolean', 'reactions' => 'array'];

    public function sender()  { return $this->belongsTo(User::class, 'sender_id'); }
    public function receiver(){ return $this->belongsTo(User::class, 'receiver_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function replyTo() { return $this->belongsTo(Message::class, 'reply_to_id'); }
}
