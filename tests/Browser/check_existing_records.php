<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$phase = $argv[1] ?? '';
if (! in_array($phase, ['before', 'after'], true) || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'pocketfinds') {
    throw new RuntimeException('Expected before/after and the existing pocketfinds MySQL database.');
}
$columns = ['users' => ['id', 'email', 'username', 'status', 'account_type', 'password'],
    'products' => ['id', 'seller_id', 'name', 'price', 'stock', 'variations', 'status'],
    'orders' => ['id', 'buyer_id', 'seller_id', 'items', 'total', 'status', 'shipping_address'],
    'shipments' => ['id', 'order_id', 'tracking_number', 'shipping_status', 'courier_id']];
$result = [];
foreach ($columns as $table => $fields) {
    $rows = \Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get($fields)->all();
    $result[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE))];
}
$path = dirname(__DIR__, 2).'/docs/teacher-test-audit/existing-records-'.$phase.'.json';
file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT));
echo 'Saved '.$phase.' record counts and checksums; no account values exported.'.PHP_EOL;
if ($phase === 'after') {
    $before = json_decode(file_get_contents(dirname($path).'/existing-records-before.json'), true);
    if ($before !== $result) {
        fwrite(STDERR, "Existing record counts or values changed; inspect before accepting the migration.\n");
        exit(1);
    }
    echo "Existing account, product, order and shipment records match the pre-migration checksums.\n";
}
