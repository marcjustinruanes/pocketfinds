<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'sender_id', 'receiver_id', 'body', 'read',
        'product_id', 'order_id', 'variation_label', 'variation_price', 'variation_image',
        'reply_to_id', 'reactions',
        'attachment_path', 'attachment_name', 'attachment_type',
        'attachment_mime', 'attachment_size',
    ];

    protected $casts = ['read' => 'boolean', 'reactions' => 'array'];

    public function gcashPurchase()
    {
        return $this->belongsTo(GcashPurchase::class, 'gcash_purchase_id');
    }

    public function displayBody(): ?string
    {
        $body = $this->body;
        $id = $this->getAttribute('gcash_purchase_id');
        $prefix = 'GCash purchase '.$id.' - amount due PHP ';
        if (! $id || ! str_starts_with((string) $body, $prefix)) {
            return $body;
        }
        $purchase = $this->gcashPurchase;
        if (! $purchase || (int) $purchase->buyer_id !== (int) $this->sender_id || (int) $purchase->seller_id !== (int) $this->receiver_id) {
            return $body;
        }

        // Present legacy system messages without rewriting retained chat history.
        return 'GCash payment for '.$purchase->productLabel().' - amount due PHP '.substr($body, strlen($prefix));
    }

    public function sender()  { return $this->belongsTo(User::class, 'sender_id'); }
    public function receiver(){ return $this->belongsTo(User::class, 'receiver_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function order()   { return $this->belongsTo(Order::class); }
    public function replyTo() { return $this->belongsTo(Message::class, 'reply_to_id'); }
}
