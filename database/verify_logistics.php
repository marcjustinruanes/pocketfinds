<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Read-only page verification against the local database; session writes roll back.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('app.env') !== 'local' || config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1') {
    throw new RuntimeException('Local MySQL required.');
}
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$admin = User::where('is_logistics', true)->where('status', 'approved')->whereIn('business_name', User::where('is_logistics', true)->where('logistics_role', 'hub_staff')->where('status', 'approved')->select('business_name'))->where(fn ($q) => $q->where('logistics_role', 'admin')->orWhereNull('logistics_role'))->firstOrFail();
$staff = User::where('is_logistics', true)->where('status', 'approved')->where('logistics_role', 'hub_staff')->where('business_name', $admin->business_name)->firstOrFail();
$failures = [];
$checks = 0;
DB::beginTransaction();
try {
    foreach (['admin' => [$admin, ['dashboard', 'transfers', 'transfers/request', 'requests', 'hub-requests', 'assignments', 'scan', 'riders', 'staff', 'hubs', 'vehicles', 'monitor', 'issues', 'history', 'reports', 'messages', 'account', 'accounts', 'activity']],
        'hub staff' => [$staff, ['dashboard', 'transfers', 'transfers/request', 'assignments', 'scan', 'hub/vehicles', 'monitor', 'issues', 'history', 'messages', 'account']],
        'platform admin' => [User::where('is_admin', true)->firstOrFail(), ['admin/dashboard', 'admin/registrations', 'admin/products', 'admin/messages', 'admin/settings']],
        'buyer' => [User::where('account_type', 'buyer')->where('status', 'approved')->firstOrFail(), ['buyer/dashboard', 'buyer/browse', 'buyer/cart', 'buyer/orders', 'buyer/messages', 'buyer/account']],
        'seller' => [User::where('account_type', 'seller')->where('status', 'approved')->firstOrFail(), ['seller/dashboard', 'seller/orders', 'seller/inventory', 'seller/messages', 'seller/account']],
        'rider' => [User::where('account_type', 'rider')->where('status', 'approved')->firstOrFail(), ['rider/dashboard', 'rider/pickup-requests', 'rider/requests', 'rider/deliveries', 'rider/messages', 'rider/account']],
    ] as $label => [$user,$paths]) {
        foreach ($paths as $path) {
            Auth::forgetGuards();
            Auth::guard()->setUser($user);
            $url = in_array($label, ['admin', 'hub staff'], true) ? '/logistics/'.$path : '/'.$path;
            $response = $kernel->handle(Request::create('http://localhost:8000'.$url, 'GET'));
            $status = $response->getStatusCode();
            echo $label.' '.$url.': '.$status.PHP_EOL;
            $checks++;
            if ($status !== 200) {
                $failures[] = $label.' '.$path;
            }
        }
    }
} finally {
    DB::rollBack();
    Auth::forgetGuards();
}
echo 'Page checks: '.$checks.', failures: '.count($failures).PHP_EOL;
exit($failures ? 1 : 0);
