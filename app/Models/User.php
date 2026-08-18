<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'account_type', 'auth_method', 'google_id',
        'last_name', 'first_name', 'middle_initial', 'sex', 'birthday', 'age',
        'email', 'contact_no',
        'province', 'municipality', 'barangay', 'house_no', 'street',
        'password', 'id_file', 'profile_picture', 'status', 'is_admin',
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
