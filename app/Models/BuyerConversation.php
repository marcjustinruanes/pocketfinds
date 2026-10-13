<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuyerConversation extends Model
{
    use HasUuids;

    protected $guarded = ['id'];
    protected $casts = ['title' => 'encrypted', 'orders_shared' => 'boolean'];

    public function messages(): HasMany
    {
        return $this->hasMany(BuyerChatMessage::class, 'conversation_id');
    }
}
