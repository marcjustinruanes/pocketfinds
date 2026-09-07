<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide settings — a single row (id 1), edited from Admin → Settings.
 * Use Setting::current() everywhere instead of hardcoding these values; it
 * creates the default row automatically if the table is ever empty.
 */
class Setting extends Model
{
    protected $fillable = [
        'platform_name', 'support_email', 'commission_rate',
        'google_signin_enabled', 'new_registrations_enabled',
        'maintenance_mode', 'email_notifications_enabled', 'updated_by',
    ];

    protected $casts = [
        'commission_rate'             => 'decimal:2',
        'google_signin_enabled'       => 'boolean',
        'new_registrations_enabled'   => 'boolean',
        'maintenance_mode'            => 'boolean',
        'email_notifications_enabled' => 'boolean',
    ];

    public function editor() { return $this->belongsTo(User::class, 'updated_by'); }

    /**
     * The one settings row, auto-created with defaults on first use. The defaults are
     * passed explicitly (not left to the migration's column defaults) so the in-memory
     * model returned here is correct immediately — firstOrCreate()'s freshly-created
     * instance only reflects attributes it actually set, not ones Postgres filled in
     * via a column default, which would otherwise leave this object's fields blank
     * until the next request re-fetches the row.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'platform_name'                => 'PocketFinds',
            'support_email'                => 'anchetanicole1020@gmail.com',
            'commission_rate'              => 10.00,
            'google_signin_enabled'        => true,
            'new_registrations_enabled'    => true,
            'maintenance_mode'             => false,
            'email_notifications_enabled'  => true,
        ]);
    }

    /** The commission rate as a 0–1 fraction, ready to multiply into revenue math. */
    public function commissionFraction(): float
    {
        return ((float) $this->commission_rate) / 100;
    }
}
