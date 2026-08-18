<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LogisticsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->updateOrInsert(
            ['email' => 'logistics@pocketfinds.com'],
            [
                'first_name'    => 'Logistics',
                'last_name'     => 'Admin',
                'email'         => 'logistics@pocketfinds.com',
                'password'      => Hash::make('logistics123'),
                'account_type'  => 'logistics',
                'auth_method'   => 'manual',
                'status'        => 'approved',
                'sex'           => 'male',
                'birthday'      => '1990-01-01',
                'age'           => 35,
                'contact_no'    => '00000000000',
                'province'      => 'N/A',
                'municipality'  => 'N/A',
                'barangay'      => 'N/A',
                'is_admin'      => false,
                'is_logistics'  => true,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );
    }
}
