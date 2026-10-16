<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$metadata = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/teacher-test-audit/mysql-audit-database.json'), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/\Apocketfinds_teacher_audit_[0-9_]+\z/', $metadata['database'])) {
    throw new RuntimeException('Invalid audit database.');
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $metadata['database']]);
DB::purge('mysql');

$email = strtolower($argv[1] ?? '');
if (! preg_match('/\A[a-zA-Z0-9.+_-]+@example\.com\z/', $email)) {
    throw new RuntimeException('Expected fictional audit email.');
}
$user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
echo json_encode(['server_date' => now()->toDateString(), 'account_count' => User::count(), 'user' => $user?->only(['id', 'given_names', 'last_name', 'email', 'contact_no', 'birthday', 'status', 'email_verified_at']),
    'token' => $user ? DB::table('account_verification_tokens')->where('user_id', $user->id)->where('purpose', 'registration')->first(['attempts', 'created_at', 'expires_at']) : null]);
