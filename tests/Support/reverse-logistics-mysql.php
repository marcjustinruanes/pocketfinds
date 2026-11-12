<?php

// Reverse-logistics failure races on disposable tables; only schema definitions are read from the configured database.
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
    if (! preg_match('/^pocketfinds_reverse_audit_[a-f0-9]{12}$/D', $name)) {
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
        $actor = User::findOrFail((int) $argv[3]);
        $shipment = \App\Models\Shipment::findOrFail($argv[4]);
        $request = \Illuminate\Http\Request::create('/reverse-fixture', 'PATCH', [
            'reason' => $argv[5], 'attempt_token' => $shipment->failedDeliveryToken(),
        ]);
        $request->setUserResolver(fn () => $actor);
        $request->setLaravelSession(app('session')->driver());
        app('redirect')->setSession($request->session());
        app()->instance('request', $request);
        \Illuminate\Support\Facades\Auth::setUser($actor);
        if ($argv[5] === 'retry') {
            $request->merge(['status' => 'assigned_to_rider', 'reason' => 'Buyer confirmed another delivery attempt']);
            app(\App\Http\Controllers\LogisticsController::class)->updateStatus($request, $shipment->id);
        } elseif ($argv[5] === 'accept') {
            app(RiderDeliveryOffers::class)->accept($actor, $shipment->id, 'delivery');
        } elseif ($argv[5] === 'deliver') {
            $proofRoot = storage_path('framework/testing/'.$argv[2]);
            config(['filesystems.disks.sensitive_documents' => ['driver' => 'local', 'root' => $proofRoot]]);
            $request->merge(['from' => 'out_for_delivery', 'delivery_otp' => '123456']);
            $request->files->set('delivery_proof', \Illuminate\Http\UploadedFile::fake()->createWithContent('delivered.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aT1sAAAAASUVORK5CYII=')));
            app(\App\Http\Controllers\RiderController::class)->advance($request, $shipment->id);
        } else {
            app(\App\Http\Controllers\RiderController::class)->markFailed($request, $shipment->id);
        }
        if (($argv[6] ?? '') === 'hold') {
            echo "UNCOMMITTED\n";
            fflush(STDOUT);
            if (trim((string) fgets(STDIN)) !== 'commit') {
                throw new RuntimeException('Missing commit barrier.');
            }
        }
        DB::commit();
        if ($argv[5] === 'deliver') {
            \Illuminate\Support\Facades\Storage::disk('sensitive_documents')->delete($shipment->fresh()->delivery_proof_file);
            @rmdir($proofRoot.'/delivery_proofs');
            @rmdir($proofRoot);
        }
        echo "RECORDED\n";
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
config(['database.connections.reverse_audit_server' => $serverConfig]);
$server = DB::connection('reverse_audit_server')->getPdo();
$live = DB::connection()->getPdo();
$definitions = [];
foreach (['users', 'logistics_hubs', 'company_vehicles', 'orders', 'shipments', 'delivery_assignments', 'notifications', 'order_status_history', 'logistics_issues'] as $table) {
    $definitions[] = $live->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
}
$name = 'pocketfinds_reverse_audit_'.bin2hex(random_bytes(6));
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
    // Foreign-key targets outside these fixture tables are intentionally absent.
    $hub = DB::table('logistics_hubs')->insertGetId(['company_name' => 'Assignment Audit', 'municipality' => 'Pila', 'province' => 'Laguna', 'is_operational' => 1]);
    $rider = DB::table('users')->insertGetId(['account_type' => 'rider', 'username' => 'assignment_audit', 'last_name' => 'Fixture', 'given_names' => 'Audit', 'sex' => 'male', 'birthday' => '1990-01-01', 'age' => 36, 'email' => 'assignment@example.test', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'Central', 'status' => 'approved', 'business_name' => 'Assignment Audit', 'logistics_hub_id' => $hub, 'is_available' => 1, 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own']);
    $seller = DB::table('users')->insertGetId(['account_type' => 'seller', 'username' => 'coverage_seller', 'last_name' => 'Fixture', 'given_names' => 'Seller', 'sex' => 'male', 'birthday' => '1990-01-01', 'age' => 36, 'email' => 'coverage-seller@example.test', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'Central', 'status' => 'approved']);
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
    $race = function (string $a, string $legA, string $b, string $legB, int $expected = 409, ?int $actorA = null, ?int $actorB = null) use (&$workers, $name, $rider, $check, $server): void {
        $input = new InputStream;
        $first = new Process([PHP_BINARY, __FILE__, '--worker', $name, (string) ($actorA ?? $rider), $a, $legA, 'hold'], base_path(), null, $input, 45);
        $second = new Process([PHP_BINARY, __FILE__, '--worker', $name, (string) ($actorB ?? $rider), $b, $legB], base_path(), null, null, 45);
        $workers = [$first, $second];
        $first->start();
        $until = microtime(true) + 20;
        while (! str_contains($first->getOutput(), 'UNCOMMITTED') && $first->isRunning() && microtime(true) < $until) {
            usleep(20000);
        }
        $check(str_contains($first->getOutput(), 'UNCOMMITTED'), 'First failure submission failed: '.$first->getErrorOutput().$first->getOutput());
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
        $check($first->isSuccessful() && str_contains($first->getOutput(), 'RECORDED'), 'First worker failed.');
        $check($second->isSuccessful() && str_contains($second->getOutput(), 'REJECTED:'.$expected), 'Competing failure submission not rejected: '.$second->getErrorOutput().$second->getOutput());
    };
    foreach ([['buyer_not_home', 'buyer_not_home'], ['buyer_not_home', 'refused']] as [$reasonA, $reasonB]) {
        $shipment = $make('delivery');
        $order = DB::table('shipments')->where('id', $shipment)->value('order_id');
        DB::table('shipments')->where('id', $shipment)->update(['courier_id' => $rider,
            'shipping_status' => 'out_for_delivery', 'out_for_delivery_at' => now(), 'delivery_attempts' => 0]);
        DB::table('orders')->where('id', $order)->update(['status' => 'out_for_delivery']);
        DB::table('delivery_assignments')->where('shipment_id', $shipment)->update(['status' => 'out_for_delivery', 'accepted_at' => now()]);
        $race($shipment, $reasonA, $shipment, $reasonB);
        $check((int) DB::table('shipments')->where('id', $shipment)->value('delivery_attempts') === 1, 'Failure counter duplicated.');
        $check(DB::table('shipments')->where('id', $shipment)->value('shipping_status') === 'delivery_failed', 'Premature returned status.');
        $check(DB::table('shipments')->where('id', $shipment)->value('delivery_failed_reason') === $reasonA, 'Competing reason overwrote the accepted observation.');
        $check(DB::table('order_status_history')->where('order_id', $order)->count() === 1, 'Duplicate failure history.');
        $duplicates = DB::table('notifications')->where('reference_id', $order)->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->get();
        $check($duplicates->isEmpty(), 'Duplicate failure notifications.');
        echo 'PASS: '.$reasonA.' versus '.$reasonB.'; observed MySQL lock wait, one committed failure, one 409, one attempt/history/notification per recipient.'.PHP_EOL;
    }
    $delivering = $make('delivery');
    $deliveringOrder = DB::table('shipments')->where('id', $delivering)->value('order_id');
    DB::table('shipments')->where('id', $delivering)->update(['courier_id' => $rider, 'shipping_status' => 'out_for_delivery',
        'out_for_delivery_at' => now(), 'delivery_otp_hash' => \Illuminate\Support\Facades\Hash::make('123456')]);
    DB::table('orders')->where('id', $deliveringOrder)->update(['status' => 'out_for_delivery', 'payment_method' => 'Prepaid fixture']);
    DB::table('delivery_assignments')->where('shipment_id', $delivering)->update(['status' => 'out_for_delivery', 'accepted_at' => now()]);
    $race($delivering, 'deliver', $delivering, 'refused');
    $check(DB::table('shipments')->where('id', $delivering)->value('shipping_status') === 'delivered', 'Failure overwrote confirmed delivery.');
    $check((int) DB::table('shipments')->where('id', $delivering)->value('delivery_attempts') === 0, 'Rejected failure changed attempt count.');
    $check(DB::table('order_status_history')->where('order_id', $deliveringOrder)->where('status', 'delivery_failed')->count() === 0, 'Failure history recorded after delivered.');
    echo 'PASS: proof/OTP-confirmed delivery versus failure; observed MySQL lock wait, delivery preserved, stale failure 409, no failed-attempt increment/history.'.PHP_EOL;
    $adminData = (array) DB::table('users')->where('id', $rider)->first();
    unset($adminData['id']);
    foreach (DB::select('SHOW COLUMNS FROM users') as $column) {
        if (str_contains($column->Extra, 'GENERATED')) unset($adminData[$column->Field]);
    }
    $adminData = array_replace($adminData, ['account_type' => 'logistics', 'is_logistics' => 1,
        'logistics_role' => 'admin', 'username' => 'reverse_admin', 'email' => 'reverse-admin@example.test']);
    $admin = DB::table('users')->insertGetId($adminData);
    $make('pickup', true);
    $make('delivery', true);
    $retry = $make('delivery');
    $offer = $make('delivery');
    DB::table('shipments')->where('id', $retry)->update(['courier_id' => $rider, 'shipping_status' => 'delivery_failed',
        'delivery_attempts' => 1, 'delivery_failed_reason' => 'buyer_not_home']);
    DB::table('orders')->where('id', DB::table('shipments')->where('id', $retry)->value('order_id'))->update(['status' => 'delivery_failed']);
    DB::table('delivery_assignments')->where('shipment_id', $retry)->update(['status' => 'out_for_delivery', 'accepted_at' => now()]);
    $race($retry, 'retry', $offer, 'accept', 422, $admin, $rider);
    $check(app(RiderDeliveryOffers::class)->activeJobs($rider) === 3, 'Retry versus acceptance exceeded combined capacity.');
    $check(DB::table('shipments')->where('id', $retry)->value('shipping_status') === 'assigned_to_rider', 'Retry did not persist.');
    $check(DB::table('shipments')->where('id', $offer)->value('courier_id') === null, 'Competing offer bypassed capacity.');
    echo 'PASS: retry versus offer acceptance at two active jobs; observed MySQL lock wait, one retry, one 422, exactly three combined jobs.'.PHP_EOL;
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop(1);
        }
    }
    DB::disconnect('mysql');
    if ($created && preg_match('/^pocketfinds_reverse_audit_[a-f0-9]{12}$/D', $name)) {
        $server->exec('DROP DATABASE `'.$name.'`');
        echo "Disposable audit database removed. No live rows copied or changed.\n";
    }
}
