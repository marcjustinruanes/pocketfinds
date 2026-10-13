<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportFaq extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['keywords' => 'array', 'is_published' => 'boolean'];

    public function scopePublishedFor($query, string $audience)
    {
        return $query->where('audience', $audience)->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }
}
