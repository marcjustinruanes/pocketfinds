<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(dirname(__DIR__, 2).'/storage/app/private/teacher-audit/checkout-concurrency.json'), true);
if (! preg_match('/\Apocketfinds_teacher_audit_[0-9_]+\z/', $fixture['database']) || ! in_array($argv[1] ?? '', ['0', '1'], true)) {
    throw new RuntimeException('Invalid audit worker parameters.');
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $fixture['database'],
    'session.driver' => 'array', 'cache.default' => 'array', 'mail.default' => 'array', 'app.debug' => false]);
\Illuminate\Support\Facades\DB::purge('mysql');
$index = (int) $argv[1];
$user = \App\Models\User::findOrFail($fixture['buyers'][$index]);
\Illuminate\Support\Facades\Auth::guard()->setUser($user);
echo json_encode(['ready' => $index]).PHP_EOL;
flush();
$deadline = microtime(true) + 20;
while (! file_exists($fixture['barrier'])) {
    if (microtime(true) >= $deadline) exit(2);
    usleep(1000);
    clearstatcache(true, $fixture['barrier']);
}
$request = \Illuminate\Http\Request::create('http://localhost/buyer/checkout', 'POST', ['items' => [$fixture['carts'][$index]], 'payment_method' => 1]);
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
echo json_encode(['worker' => $index, 'http_status' => $response->getStatusCode(), 'error' => $request->session()->get('error'),
    'orders' => \App\Models\Order::where('buyer_id', $user->id)->count()]).PHP_EOL;
