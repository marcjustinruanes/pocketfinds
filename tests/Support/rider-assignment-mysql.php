<?php

// Two-process verification on disposable tables cloned from local MySQL definitions.
use App\Models\User;
use App\Services\RiderDeliveryOffers;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('app.env') !== 'local' || config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1') {
    throw new RuntimeException('Local MySQL required.');
}
$original = config('database.connections.mysql');
$connect = function (string $name): void {
    if (! preg_match('/^pocketfinds_assignment_audit_[a-f0-9]{12}$/D', $name)) {
        throw new RuntimeException('Disposable database required.');
    }
    config(['database.connections.mysql.database' => $name, 'database.connections.mysql.url' => null, 'cache.default' => 'array', 'mail.default' => 'array']);
    DB::purge('mysql');
};
$check = function (bool $ok, string $message): void {
    if (! $ok) {
        throw new RuntimeException($message);
    }
};
if (($argv[1] ?? '') === '--worker') {
    $connect($argv[2]);
    try {
        DB::beginTransaction();
        // Establish a repeatable-read snapshot before waiting on the competing rider lock.
        DB::table('shipments')->count();
        echo "ATTEMPT\n";
        fflush(STDOUT);
        app(RiderDeliveryOffers::class)->accept(User::findOrFail((int) $argv[3]), $argv[4], $argv[5]);
        if (($argv[6] ?? '') === 'hold') {
            echo "UNCOMMITTED\n";
            fflush(STDOUT);
            if (trim((string) fgets(STDIN)) !== 'commit') {
                throw new RuntimeException('Missing commit barrier.');
            }
        }
        DB::commit();
        echo "ACCEPTED\n";
    } catch (HttpExceptionInterface $error) {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
        echo 'REJECTED:'.$error->getStatusCode().PHP_EOL;
    } catch (Throwable $error) {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
        fwrite(STDERR, $error->getMessage());
        exit(1);
    }
    exit;
}
$serverConfig = $original;
$serverConfig['database'] = null;
$serverConfig['url'] = null;
config(['database.connections.assignment_audit_server' => $serverConfig]);
$server = DB::connection('assignment_audit_server')->getPdo();
$live = DB::connection()->getPdo();
$definitions = [];
foreach (['users', 'logistics_hubs', 'company_vehicles', 'orders', 'shipments', 'delivery_assignments', 'notifications', 'order_status_history'] as $table) {
    $definitions[] = $live->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
}
$name = 'pocketfinds_assignment_audit_'.bin2hex(random_bytes(6));
$created = false;
$workers = [];
try {
    $server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $connect($name);
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    foreach ($definitions as $ddl) {
        DB::unprepared($ddl);
    }
    (require database_path('migrations/2026_11_11_000001_add_rider_barangay_routes.php'))->up();
    // Foreign-key targets outside these eight fixture tables are intentionally absent.
    $hub = DB::table('logistics_hubs')->insertGetId(['company_name' => 'Assignment Audit', 'municipality' => 'Pila', 'province' => 'Laguna', 'is_operational' => 1]);
    $rider = DB::table('users')->insertGetId(['account_type' => 'rider', 'username' => 'assignment_audit', 'last_name' => 'Fixture', 'given_names' => 'Audit', 'sex' => 'male', 'birthday' => '1990-01-01', 'age' => 36, 'email' => 'assignment@example.test', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'Central', 'status' => 'approved', 'business_name' => 'Assignment Audit', 'logistics_hub_id' => $hub, 'is_available' => 1, 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own']);
    $seller = DB::table('users')->insertGetId(['account_type' => 'seller', 'username' => 'coverage_seller', 'last_name' => 'Fixture', 'given_names' => 'Seller', 'sex' => 'male', 'birthday' => '1990-01-01', 'age' => 36, 'email' => 'coverage-seller@example.test', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'Central', 'status' => 'approved']);
    foreach (['043424001' => 'Central', '043424002' => 'Santa Clara'] as $code => $barangay) {
        DB::table('rider_barangay_routes')->insert(['rider_id' => $rider, 'hub_id' => $hub, 'barangay_code' => $code, 'barangay_name' => $barangay, 'barangay_key' => mb_strtolower($barangay), 'created_at' => now(), 'updated_at' => now()]);
    }
    $make = function (string $leg, bool $accepted = false) use ($rider, $seller): string {
        $order = (string) Str::uuid();
        $shipment = (string) Str::uuid();
        $state = $leg === 'pickup' ? ($accepted ? 'picked_up' : 'ready_for_pickup') : ($accepted ? 'assigned_to_rider' : 'sorted');
        DB::table('orders')->insert(['id' => $order, 'buyer_id' => $rider, 'seller_id' => $seller, 'shipping_address' => json_encode(['barangay' => 'Santa Clara', 'municipality' => 'Pila', 'province' => 'Laguna']), 'payment_method' => 'Cash on Delivery', 'status' => $state, 'items' => '[]']);
        DB::table('shipments')->insert(['id' => $shipment, 'order_id' => $order, 'logistics_company' => 'Assignment Audit', 'origin_hub' => 'Pila', 'origin_province' => 'Laguna', 'destination_hub' => 'Pila', 'destination_province' => 'Laguna', 'shipping_status' => $state, 'pickup_approved_at' => now(), 'pickup_rider_id' => $accepted && $leg === 'pickup' ? $rider : null, 'courier_id' => $accepted && $leg === 'delivery' ? $rider : null]);
        if (! $accepted) {
            DB::table('delivery_assignments')->insert(['id' => (string) Str::uuid(), 'shipment_id' => $shipment, 'courier_id' => $rider, 'leg' => $leg, 'status' => 'offered', 'expires_at' => now()->addMinutes(5)]);
        }

        return $shipment;
    };
    $race = function (string $a, string $legA, string $b, string $legB) use (&$workers, $name, $rider, $check, $server): void {
        $input = new InputStream;
        $first = new Process([PHP_BINARY, __FILE__, '--worker', $name, (string) $rider, $a, $legA, 'hold'], base_path(), null, $input, 45);
        $second = new Process([PHP_BINARY, __FILE__, '--worker', $name, (string) $rider, $b, $legB], base_path(), null, null, 45);
        $workers = [$first, $second];
        $first->start();
        $until = microtime(true) + 20;
        while (! str_contains($first->getOutput(), 'UNCOMMITTED') && $first->isRunning() && microtime(true) < $until) {
            usleep(20000);
        }
        $check(str_contains($first->getOutput(), 'UNCOMMITTED'), 'First acceptance failed: '.$first->getErrorOutput().$first->getOutput());
        $second->start();
        $waited = false;
        $until = microtime(true) + 20;
        while ($second->isRunning() && microtime(true) < $until) {
            $query = $server->prepare('SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE WHERE l.OBJECT_SCHEMA=?');
            $query->execute([$name]);
            if ((int) $query->fetchColumn() > 0) {
                $waited = true;
                break;
            }
            usleep(20000);
        }
        $check($waited, 'No MySQL row lock wait observed: '.$second->getErrorOutput().$second->getOutput());
        $input->write("commit\n");
        $input->close();
        $first->wait();
        $second->wait();
        $check($first->isSuccessful() && str_contains($first->getOutput(), 'ACCEPTED'), 'First worker failed.');
        $check($second->isSuccessful() && str_contains($second->getOutput(), 'REJECTED:422'), 'Competing acceptance not rejected: '.$second->getErrorOutput().$second->getOutput());
    };
    $make('pickup', true);
    $make('delivery', true);
    $a = $make('pickup');
    $b = $make('delivery');
    $race($a, 'pickup', $b, 'delivery');
    $check(app(RiderDeliveryOffers::class)->activeJobs($rider) === 3, 'Combined capacity exceeded.');
    echo "PASS: competing pickup/delivery acceptance at two active jobs; real lock wait, one acceptance, one 422, exactly three active jobs.\n";
    DB::table('shipments')->update(['pickup_rider_id' => null, 'courier_id' => null, 'shipping_status' => 'sorted']);
    $c = $make('delivery');
    $race($c, 'delivery', $c, 'delivery');
    $check(DB::table('order_status_history')->where('order_id', DB::table('shipments')->where('id', $c)->value('order_id'))->count() === 1, 'Duplicate status history.');
    echo "PASS: duplicate parcel acceptance; real lock wait, one acceptance, one 422, one history event.\n";
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop(1);
        }
    }
    DB::disconnect('mysql');
    if ($created && preg_match('/^pocketfinds_assignment_audit_[a-f0-9]{12}$/D', $name)) {
        $server->exec('DROP DATABASE `'.$name.'`');
        echo "Disposable audit database removed. No live rows copied or changed.\n";
    }
}
