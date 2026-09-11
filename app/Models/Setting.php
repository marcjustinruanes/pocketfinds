<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'platform_name', 'support_email', 'commission_rate',
        'google_signin_enabled', 'new_registrations_enabled',
        'maintenance_mode', 'email_notifications_enabled',
        'updated_by',
    ];

    protected $casts = [
        'commission_rate'             => 'decimal:2',
        'google_signin_enabled'       => 'boolean',
        'new_registrations_enabled'   => 'boolean',
        'maintenance_mode'            => 'boolean',
        'email_notifications_enabled' => 'boolean',
    ];

    /** The platform only ever has one settings row. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['platform_name' => 'PocketFinds']);
    }
}
