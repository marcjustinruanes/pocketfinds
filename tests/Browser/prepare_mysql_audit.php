<?php

// Create a separate MySQL database; never reset or seed the configured pocketfinds database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || (string) config('database.connections.mysql.port') !== '3307') {
    throw new RuntimeException('Expected the configured local MySQL connection on port 3307.');
}
$name = 'pocketfinds_teacher_audit_'.gmdate('Ymd_His');
\Illuminate\Support\Facades\DB::statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
config(['database.connections.mysql.database' => $name]);
\Illuminate\Support\Facades\DB::purge('mysql');
$exit = \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
file_put_contents(dirname(__DIR__, 2).'/docs/teacher-test-audit/mysql-migrations.txt', \Illuminate\Support\Facades\Artisan::output());
if ($exit !== 0) {
    throw new RuntimeException('Audit migration failed. The separate audit database has been retained for inspection.');
}
\Illuminate\Support\Facades\DB::table('settings')->insert(['platform_name' => 'PocketFinds']);
\Illuminate\Support\Facades\DB::table('categories')->insert(['id' => 1, 'name' => 'Home & Living']);
\Illuminate\Support\Facades\DB::table('id_types')->insert(['id' => 1, 'name' => "Driver's License"]);
\Illuminate\Support\Facades\DB::table('payment_methods')->insert(['id' => 1, 'name' => 'Cash on Delivery', 'type' => 'cod', 'is_active' => true]);
\Illuminate\Support\Facades\DB::table('vehicle_types')->insert(['id' => 1, 'slug' => 'two_wheels', 'name' => 'Motorcycle']);
$hub = \App\Models\LogisticsHub::create(['company_name' => 'Audit Logistics', 'province' => 'Metro Manila',
    'municipality' => 'Makati City', 'is_operational' => true, 'is_hiring' => true]);
foreach (['buyer', 'seller', 'rider', 'logistics'] as $role) {
    \App\Models\User::create(['account_type' => $role, 'username' => 'audit_'.$role, 'email' => $role.'@example.com',
        'given_names' => 'Teacher Audit', 'last_name' => ucfirst($role), 'password' => 'TeacherAudit@2026',
        'status' => 'approved', 'sex' => 'male', 'birthday' => '1998-05-14', 'age' => 28, 'contact_no' => '09171234567',
        'province' => 'Metro Manila', 'municipality' => 'Makati City', 'barangay' => 'Bel-Air', 'street' => 'Audit Street',
        'category_id' => 1, 'business_name' => in_array($role, ['rider', 'logistics']) ? 'Audit Logistics' : 'Audit '.ucfirst($role),
        'logistics_hub_id' => in_array($role, ['rider', 'logistics']) ? $hub->id : null, 'is_logistics' => $role === 'logistics',
        'logistics_role' => $role === 'logistics' ? 'admin' : null]);
}
$metadata = ['database' => $name, 'port' => 3307, 'migrations_verified' => true];
file_put_contents(dirname(__DIR__, 2).'/docs/teacher-test-audit/mysql-audit-database.json', json_encode($metadata, JSON_PRETTY_PRINT));
echo json_encode($metadata).PHP_EOL;
