<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Announcement extends Model
{
    // `announcements.id` is a Postgres uuid — see Order.php for why this is needed.
    protected $keyType      = 'string';
    public    $incrementing = false;

    protected $fillable = ['title', 'body', 'audience', 'is_active', 'created_by'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
