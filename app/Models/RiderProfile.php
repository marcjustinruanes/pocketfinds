<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderProfile extends Model
{
    protected $fillable = [
        'user_id',
        // copied from users
        'auth_method', 'google_id', 'username',
        'last_name', 'given_names', 'middle_name',
        'sex', 'birthday', 'age',
        'email', 'contact_no',
        'province', 'municipality', 'barangay', 'house_no', 'street',
        'password', 'id_file', 'id_type_id', 'selfie_file',
        // vehicle
        'vehicle_type', 'vehicle_brand', 'vehicle_model',
        'plate_number', 'or_file', 'cr_file',
        // license
        'license_number', 'license_expiry', 'license_file',
        // status
        'status',
    ];

    protected $casts = [
        'birthday'       => 'date',
        'license_expiry' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** True if the vehicle requires a driver's license */
    public function requiresLicense(): bool
    {
        return in_array($this->vehicle_type, ['motorcycle', 'car_van']);
    }
}
