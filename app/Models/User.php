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
        'status', 'is_admin', 'is_logistics', 'category_id',
        'profile_picture', 'business_name', 'business_permit_file',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'birthday'          => 'date',
        ];
    }
}
