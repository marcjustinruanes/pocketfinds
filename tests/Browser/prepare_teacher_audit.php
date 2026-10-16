<?php

// Build a separate, disposable browser fixture. Never connect to the user's MySQL database.
$root = dirname(__DIR__, 2);
$directory = $root.'/storage/app/private/teacher-audit';
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$database = $directory.'/browser.sqlite';
if (file_exists($database) && in_array('--reset', $argv, true)) {
    unlink($database);
}
if (file_exists($database)) {
    fwrite(STDERR, "Audit database already exists; refusing to overwrite it.\n");
    exit(1);
}
touch($database);
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
    'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'MAIL_MAILER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema = new class
{
    use \Tests\Support\WorkflowSchema;

    public function assertSame($expected, $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Browser fixtures require SQLite.');
        }
    }

    public function install(): void
    {
        $this->createWorkflowSchema();
    }
};
$schema->install();
(require $root.'/database/migrations/2026_10_14_000004_add_gcash_purchases.php')->up();
(require $root.'/database/migrations/2026_10_16_000001_add_account_verification.php')->up();
\Illuminate\Support\Facades\DB::table('settings')->insert(['platform_name' => 'PocketFinds']);
\Illuminate\Support\Facades\DB::table('categories')->insert(['id' => 1, 'name' => 'Home & Living']);
\Illuminate\Support\Facades\DB::table('id_types')->insert(['id' => 1, 'name' => "Driver's License"]);
\Illuminate\Support\Facades\DB::table('payment_methods')->insert(['id' => 1, 'name' => 'Cash on Delivery', 'is_active' => true]);
$hub = \App\Models\LogisticsHub::create(['company_name' => 'Audit Logistics', 'province' => 'Metro Manila',
    'municipality' => 'Makati City', 'is_operational' => true, 'is_hiring' => true]);
foreach (['buyer', 'seller', 'rider', 'logistics'] as $role) {
    \App\Models\User::create(['account_type' => $role, 'username' => 'audit_'.$role, 'email' => $role.'@example.com',
        'given_names' => 'Teacher Audit', 'last_name' => ucfirst($role), 'password' => 'TeacherAudit@2026',
        'status' => 'approved', 'sex' => 'male', 'birthday' => '1998-05-14', 'contact_no' => '09171234567',
        'province' => 'Metro Manila', 'municipality' => 'Makati City', 'barangay' => 'Bel-Air', 'street' => 'Audit Street',
        'category_id' => 1, 'business_name' => in_array($role, ['rider', 'logistics']) ? 'Audit Logistics' : 'Audit '.ucfirst($role),
        'logistics_hub_id' => in_array($role, ['rider', 'logistics']) ? $hub->id : null, 'is_logistics' => $role === 'logistics',
        'logistics_role' => $role === 'logistics' ? 'admin' : null]);
}
echo "Created isolated browser fixtures: ".$database.PHP_EOL;
