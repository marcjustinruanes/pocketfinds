<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopFollow extends Model
{
    // `shop_follows.id` is a Postgres uuid with a gen_random_uuid() default,
    // and the table has no updated_at column — see Announcement.php for the
    // uuid half of this pattern.
    protected $keyType      = 'string';
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = ['buyer_id', 'seller_id'];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
