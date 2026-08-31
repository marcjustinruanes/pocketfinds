<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'account_type', 'auth_method', 'google_id', 'username',
        'last_name', 'given_names', 'middle_name', 'sex', 'birthday', 'age',
        'email', 'contact_no',
        'province', 'municipality', 'barangay', 'house_no', 'street',
        'password', 'id_file', 'id_type_id', 'selfie_file',
        'status', 'is_admin', 'is_logistics', 'category_id', 'category_other',
        'profile_picture', 'business_name', 'business_permit_file', 'shipping_fee',
        // rider-only fields (merged in from the now-dropped rider_profiles table)
        'vehicle_type', 'vehicle_brand', 'vehicle_model', 'plate_number',
        'or_file', 'cr_file', 'license_number', 'license_expiry', 'license_file',
        // logistics-only settings
        'notify_new_requests', 'notify_unassigned_shipments', 'preferred_scanner',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'birthday'                    => 'date',
            'license_expiry'              => 'date',
            'is_admin'                    => 'boolean',
            'is_logistics'                => 'boolean',
            'notify_new_requests'         => 'boolean',
            'notify_unassigned_shipments' => 'boolean',
        ];
    }

    /** True if this rider's vehicle type requires a driver's license (mirrors the old RiderProfile helper). */
    public function requiresLicense(): bool
    {
        return in_array($this->vehicle_type, ['motorcycle', 'car_van'], true);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
