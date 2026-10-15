<?php

use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\User;
use App\Services\HubTransferWorkflow;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('app.env') !== 'local' || config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1') {
    throw new RuntimeException('Local MySQL is required.');
}
$original = config('database.connections.mysql');
$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$connect = function (string $name) {
    if (! preg_match('/^pocketfinds_transfer_audit_[0-9a-f]{12}$/D', $name)) {
        throw new RuntimeException('Only a disposable audit database is permitted.');
    }
    config(['database.connections.mysql.database' => $name, 'database.connections.mysql.url' => null, 'mail.default' => 'array', 'session.driver' => 'array', 'cache.default' => 'array']);
    DB::purge('mysql');
};
if (($argv[1] ?? '') === '--worker') {
    $connect($argv[2]);
    try {
        $actor = User::findOrFail((int) $argv[4]);
        $operation = $argv[7] ?? 'dispatch';
        $apply = fn () => $operation === 'receive' ? app(HubTransferWorkflow::class)->receive($actor, $argv[3]) : app(HubTransferWorkflow::class)->dispatch($actor, $argv[3], (int) $argv[5]);
        if (($argv[6] ?? '') === 'hold') {
            DB::beginTransaction();
            $apply();
            echo "UNCOMMITTED\n";
            fflush(STDOUT);
            if (trim((string) fgets(STDIN)) !== 'commit') {
                throw new RuntimeException('Missing commit barrier.');
            }
            DB::commit();
            echo $operation === 'receive' ? "RECEIVED\n" : "DISPATCHED\n";
        } else {
            echo "ATTEMPT\n";
            fflush(STDOUT);
            $apply();
            echo $operation === 'receive' ? "RECEIVED\n" : "DISPATCHED\n";
        }
    } catch (HttpExceptionInterface $error) {
        echo 'REJECTED:'.$error->getStatusCode().PHP_EOL;
    } catch (Throwable $error) {
        fwrite(STDERR, 'Worker failed: '.$error->getMessage().PHP_EOL);
        exit(1);
    }
    exit;
}

$serverConfig = $original;
$serverConfig['database'] = null;
$serverConfig['url'] = null;
config(['database.connections.transfer_audit_server' => $serverConfig]);
$server = DB::connection('transfer_audit_server')->getPdo();
$name = 'pocketfinds_transfer_audit_'.bin2hex(random_bytes(6));
$created = false;
$workers = [];
$failure = null;
try {
    if (in_array('--live-schema', $argv, true)) {
        $check(! Schema::hasTable('hub_transfer_batches'), 'Use --live-schema before installing the transfer migration.');
        $live = DB::connection()->getPdo();
        $ddl = [];
        foreach (Schema::getTableListing(DB::connection()->getDatabaseName(), false) as $table) {
            $ddl[] = [$live->query('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`')->fetch(PDO::FETCH_NUM)[1], $table];
        }
        echo 'Using '.count($ddl).' live table definitions; no live rows are copied.'.PHP_EOL;
    } else {
        preg_match_all('/^CREATE TABLE `([^`]+)` \(.*?^\) ENGINE=[^\r\n]+;/ms', file_get_contents(database_path('pocketfinds-mysql-schema.sql')), $ddl, PREG_SET_ORDER);
        $check(count($ddl) === 43, 'Unexpected baseline table count: '.count($ddl));
    }
    $server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $connect($name);
    $pdo = DB::connection()->getPdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($ddl as $table) {
        $pdo->exec($table[0]);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    if (! Schema::hasTable('logistics_activity_logs')) {
        (require database_path('migrations/2026_10_13_000001_add_logistics_operations.php'))->up();
    }
    $origin = LogisticsHub::create(['company_name' => 'Audit Company', 'municipality' => 'Santa Cruz', 'province' => 'Laguna', 'is_operational' => true]);
    $destination = LogisticsHub::create(['company_name' => 'Audit Company', 'municipality' => 'Santa Cruz', 'province' => 'Marinduque', 'is_operational' => true]);
    $makeUser = function ($role, $hub, $suffix) {
        return User::create(['username' => 'audit_'.$suffix, 'account_type' => in_array($role, ['logistics_admin', 'hub_staff']) ? 'logistics' : $role,
            'is_admin' => $role === 'admin', 'is_logistics' => in_array($role, ['logistics_admin', 'hub_staff']), 'logistics_role' => $role === 'logistics_admin' ? 'admin' : ($role === 'hub_staff' ? 'hub_staff' : null),
            'business_name' => 'Audit Company', 'logistics_hub_id' => $hub->id, 'given_names' => 'Audit', 'last_name' => 'Fixture', 'sex' => 'male', 'birthday' => '2000-01-01', 'age' => 26,
            'email' => $suffix.'@example.test', 'contact_no' => '09123456789', 'province' => $hub->province, 'municipality' => $hub->municipality, 'barangay' => 'Test', 'status' => 'approved',
            'password' => 'DisposableFixturePassword123', 'vehicle_type' => 'four_wheels', 'vehicle_ownership' => 'own']);
    };
    $admin = $makeUser('logistics_admin', $origin, 'admin');
    $secondAdmin = $makeUser('logistics_admin', $origin, 'second_admin');
    $sender = $makeUser('hub_staff', $origin, 'sender');
    $receiver = $makeUser('hub_staff', $destination, 'receiver');
    $secondReceiver = $makeUser('hub_staff', $destination, 'second_receiver');
    $rider = $makeUser('rider', $origin, 'rider');
    $buyer = $makeUser('buyer', $origin, 'buyer');
    $seller = $makeUser('seller', $origin, 'seller');
    $platform = $makeUser('admin', $origin, 'platform');
    $makeParcel = function ($suffix) use ($buyer, $seller, $origin, $destination) {
        $order = Order::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'payment_method' => 'Cash on delivery', 'status' => 'at_sorting_center', 'items' => []]);
        $shipment = Shipment::create(['order_id' => $order->id, 'tracking_number' => 'AUDIT-'.$suffix, 'logistics_company' => 'Audit Company', 'origin_hub' => $origin->municipality, 'origin_province' => $origin->province,
            'destination_hub' => $destination->municipality, 'destination_province' => $destination->province, 'shipping_status' => 'at_sorting_center']);
        $leg = ShipmentHubLeg::create(['shipment_id' => $shipment->id, 'sequence' => 1, 'leg_type' => 'relay', 'from_hub' => $origin->municipality, 'from_province' => $origin->province,
            'to_hub' => $destination->municipality, 'to_province' => $destination->province, 'status' => 'pending']);

        return [$shipment, $leg];
    };
    [$legacy, $legacyLeg] = $makeParcel('LEGACY');
    $legacyLeg->update(['requested_at' => now(), 'requested_by' => $sender->id, 'approved_at' => now(), 'approved_by' => $admin->id]);
    $snapshots = [];
    foreach ($ddl as $table) {
        $columns = Schema::getColumnListing($table[1]);
        $snapshots[$table[1]] = [$columns, DB::table($table[1])->select($columns)->get()->map(fn ($row) => serialize($row))->sort()->values()->all()];
    }
    (require database_path('migrations/2026_10_14_000001_add_hub_transfer_batches.php'))->up();
    foreach ($snapshots as $table => [$columns, $expected]) {
        $actual = DB::table($table)->select($columns)->get()->map(fn ($row) => serialize($row))->sort()->values()->all();
        $check($actual === $expected, 'Migration altered original column values in '.$table);
    }
    $check($legacyLeg->fresh()->transferBatch->status === 'approved', 'Existing valid approval was not imported.');
    echo 'Migration: original column values preserved across '.count($snapshots)." fixture tables; legacy approval imported.\n";

    [$a, $legA] = $makeParcel('A');
    [$b, $legB] = $makeParcel('B');
    $flow = app(HubTransferWorkflow::class);
    $batch = $flow->request($sender, $origin->id, $destination->id, [$a->id, $b->id]);
    $flow->decide($admin, $batch->id, 'approved');
    $check(ShipmentHubLeg::whereIn('id', [$legA->id, $legB->id])->where('status', 'pending')->count() === 2, 'Approval dispatched parcels.');

    $input = new InputStream;
    $first = new Process([PHP_BINARY, __FILE__, '--worker', $name, $batch->id, (string) $admin->id, (string) $rider->id, 'hold'], base_path(), null, $input, 45);
    $second = new Process([PHP_BINARY, __FILE__, '--worker', $name, $batch->id, (string) $secondAdmin->id, (string) $rider->id], base_path(), null, null, 45);
    $workers = [$first, $second];
    $first->start();
    $until = microtime(true) + 20;
    while (! str_contains($first->getOutput(), 'UNCOMMITTED') && $first->isRunning() && microtime(true) < $until) {
        usleep(20000);
    }
    $check(str_contains($first->getOutput(), 'UNCOMMITTED'), 'First worker failed: '.$first->getErrorOutput());
    $second->start();
    $waited = false;
    $until = microtime(true) + 20;
    while ($second->isRunning() && microtime(true) < $until) {
        $q = $server->prepare('SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE = w.ENGINE WHERE l.OBJECT_SCHEMA = ?');
        $q->execute([$name]);
        if ((int) $q->fetchColumn() > 0) {
            $waited = true;
            break;
        }
        $first->getOutput();
        $second->getOutput();
        usleep(20000);
    }
    $check($waited, 'A real MySQL lock wait was not observed.');
    $input->write("commit\n");
    $input->close();
    $first->wait();
    $second->wait();
    $check($first->isSuccessful() && str_contains($first->getOutput(), 'DISPATCHED'), 'First dispatch did not commit.');
    $check($second->isSuccessful() && str_contains($second->getOutput(), 'REJECTED:422'), 'Concurrent duplicate dispatch was not rejected.');
    $check($batch->events()->where('action', 'dispatched')->count() === 1, 'Duplicate dispatch history found.');
    echo "Concurrency: observed MySQL row-lock wait; one dispatch committed, competing dispatch rejected (422).\n";
    $flow->confirmPickup($rider, $legA->id);
    $flow->confirmPickup($rider, $legB->id);
    $flow->receive($receiver, $batch->id, [$a->id]);
    $check($batch->fresh()->status === 'in_transit', 'Partial receipt incorrectly completed batch.');
    $input = new InputStream;
    $first = new Process([PHP_BINARY, __FILE__, '--worker', $name, $batch->id, (string) $receiver->id, '0', 'hold', 'receive'], base_path(), null, $input, 45);
    $second = new Process([PHP_BINARY, __FILE__, '--worker', $name, $batch->id, (string) $secondReceiver->id, '0', 'run', 'receive'], base_path(), null, null, 45);
    $workers = [$first, $second];
    $first->start();
    $until = microtime(true) + 20;
    while (! str_contains($first->getOutput(), 'UNCOMMITTED') && $first->isRunning() && microtime(true) < $until) {
        usleep(20000);
    }
    $check(str_contains($first->getOutput(), 'UNCOMMITTED'), 'First receipt worker failed: '.$first->getErrorOutput());
    $second->start();
    $waited = false;
    $until = microtime(true) + 20;
    while ($second->isRunning() && microtime(true) < $until) {
        $q->execute([$name]);
        if ((int) $q->fetchColumn() > 0) {
            $waited = true;
            break;
        }
        $first->getOutput();
        $second->getOutput();
        usleep(20000);
    }
    $check($waited, 'A real MySQL receipt lock wait was not observed.');
    $input->write("commit\n");
    $input->close();
    $first->wait();
    $second->wait();
    $check($first->isSuccessful() && str_contains($first->getOutput(), 'RECEIVED') && $second->isSuccessful() && str_contains($second->getOutput(), 'REJECTED:422'), 'Concurrent duplicate receipt was not rejected.');
    $check($batch->events()->where('action', 'received')->count() === 1, 'Duplicate arrival history found.');
    echo "Concurrency: observed receipt row-lock wait; one final receipt committed, competing receipt rejected (422).\n";
    $check($batch->fresh()->status === 'completed' && $a->fresh()->shipping_status === 'sorted' && $b->fresh()->shipping_status === 'sorted', 'Receipt state mismatch.');
    echo "MySQL workflow: request, separate approval/dispatch, rider pickup, partial and final receipt passed.\n";

    Mail::fake();
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $checks = 0;
    foreach ([$admin, $sender, $receiver, $platform, $buyer, $seller, $rider] as $user) {
        foreach (['/logistics/transfers', '/logistics/transfers/'.$batch->id] as $path) {
            Auth::forgetGuards();
            Auth::guard()->setUser($user);
            $response = $kernel->handle(Request::create('http://localhost'.$path, 'GET'));
            $allowed = $user->is_logistics && ! $user->is_admin;
            $check($allowed ? $response->getStatusCode() === 200 : in_array($response->getStatusCode(), [302, 303, 401, 403, 404], true), 'Unexpected transfer page authorization.');
            $checks++;
        }
    }
    echo 'Transfer page/role checks: '.$checks.' passed; all six roles represented.'.PHP_EOL;
} catch (Throwable $error) {
    $failure = $error;
    fwrite(STDERR, 'Verification failed: '.$error->getMessage().PHP_EOL);
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop(1);
        }
    }
    config(['database.connections.mysql' => $original]);
    DB::purge('mysql');
    if ($created && preg_match('/^pocketfinds_transfer_audit_[0-9a-f]{12}$/D', $name)) {
        $server->exec('DROP DATABASE `'.$name.'`');
        echo "Disposable database removed; live database was not modified.\n";
    }
}
exit($failure ? 1 : 0);
