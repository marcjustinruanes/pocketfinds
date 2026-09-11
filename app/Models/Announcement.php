<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

    /**
     * Fans this announcement out as one row per targeted user in the
     * existing notifications table (notification_type 'announcement',
     * reference_id = this announcement's id) — the same table/shape every
     * other notification already uses, so the notification bell, unread
     * counts, and openNotification() all pick these up for free. Reused by
     * both the admin and seller announcement flows; never create a
     * user-facing Announcement without also calling this, or it silently
     * never reaches anyone's notifications.
     */
    public function notifyAudience(): void
    {
        $types = $this->audience === 'all' ? ['buyer', 'seller', 'rider'] : [$this->audience];

        $userIds = User::whereIn('account_type', $types)->where('status', 'approved')->pluck('id');
        if ($userIds->isEmpty()) return;

        $now  = now();
        $rows = $userIds->map(fn ($id) => [
            'id'                => (string) Str::uuid(),
            'user_id'           => $id,
            'title'             => 'New Announcement',
            'message'           => $this->title,
            'notification_type' => 'announcement',
            'reference_id'      => $this->id,
            'is_read'           => false,
            'created_at'        => $now,
        ])->all();

        // A platform-wide "all" announcement can target thousands of users —
        // chunk the insert rather than one unbounded query.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }
    }
}
