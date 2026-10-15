<?php

use App\Http\Controllers\LogisticsController;
use App\Models\LogisticsHub;
use App\Models\User;
use App\Services\HubHiring;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('app.env') !== 'local' || config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1') {
    throw new RuntimeException('Only local MySQL is permitted.');
}
$original = config('database.connections.mysql');
$connect = function (string $name) {
    if (! preg_match('/^pocketfinds_hiring_audit_[0-9a-f]{12}$/D', $name)) {
        throw new RuntimeException('Only a disposable hiring database is permitted.');
    }
    config(['database.connections.mysql.database' => $name, 'database.connections.mysql.url' => null, 'mail.default' => 'array', 'session.driver' => 'array', 'cache.default' => 'array']);
    DB::purge('mysql');
};
$operation = function (array $payload) {
    if ($payload['op'] === 'approve') {
        User::findOrFail($payload['id'])->update(['status' => 'approved']);
    } elseif ($payload['op'] === 'settings') {
        $actor = User::findOrFail($payload['actor']);
        Auth::login($actor);
        $request = Request::create('/logistics/hubs/'.$payload['hub'].'/toggle-hiring', 'PATCH', $payload['data']);
        $request->setUserResolver(fn () => $actor);
        app(LogisticsController::class)->toggleHubHiring($request, $payload['hub']);
    } else {
        app(HubHiring::class)->register($payload['data'], 'rider');
    }
};
if (($argv[1] ?? '') === '--worker') {
    $connect($argv[2]);
    $payload = json_decode(base64_decode($argv[3]), true, flags: JSON_THROW_ON_ERROR);
    try {
        // Establish an earlier REPEATABLE READ snapshot, as upload middleware can do.
        DB::beginTransaction();
        DB::table('users')->count();
        $operation($payload);
        if (($argv[4] ?? '') === 'hold') {
            echo "UNCOMMITTED\n";
            fflush(STDOUT);
            if (trim((string) fgets(STDIN)) !== 'commit') {
                throw new RuntimeException('Commit barrier missing.');
            }
        }
        DB::commit();
        echo "COMMITTED\n";
    } catch (ValidationException $e) {
        if (DB::transactionLevel()) {
            DB::rollBack(0);
        }
        echo "REJECTED:422\n";
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage().PHP_EOL);
        exit(1);
    }
    exit;
}
$check = function (bool $condition, string $message) {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$serverConfig = $original;
$serverConfig['database'] = null;
$serverConfig['url'] = null;
config(['database.connections.hiring_audit_server' => $serverConfig]);
$server = DB::connection('hiring_audit_server')->getPdo();
$name = 'pocketfinds_hiring_audit_'.bin2hex(random_bytes(6));
$created = false;
$workers = [];
$failure = null;
try {
    $live = DB::connection()->getPdo();
    $ddl = [];
    foreach (Schema::getTableListing(DB::connection()->getDatabaseName(), false) as $table) {
        $ddl[$table] = $live->query('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`')->fetch(PDO::FETCH_NUM)[1];
    }
    echo 'Cloning '.count($ddl).' local table definitions; no live rows copied.'.PHP_EOL;
    $server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $connect($name);
    $pdo = DB::connection()->getPdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($ddl as $sql) {
        $pdo->exec($sql);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $hub = LogisticsHub::create(['company_name' => 'Audit Company', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'is_hiring' => true, 'is_operational' => true]);
    $makeUser = function (string $suffix, string $status = 'pending', bool $admin = false) use ($hub) {
        return User::create(['account_type' => $admin ? 'logistics' : 'rider', 'is_admin' => false, 'is_logistics' => $admin,
            'logistics_role' => $admin ? 'admin' : null, 'business_name' => $hub->company_name, 'logistics_hub_id' => $hub->id,
            'username' => 'audit_'.$suffix, 'email' => $suffix.'@gmail.com', 'given_names' => 'New', 'last_name' => $suffix,
            'sex' => 'male', 'birthday' => '2000-01-01', 'age' => 26, 'password' => 'DisposablePassword123', 'status' => $status,
            'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Test', 'contact_no' => '09123456789', 'vehicle_type' => 'four_wheels', 'vehicle_ownership' => 'own']);
    };
    $admin = $makeUser('admin', 'approved', true);
    $firstApplicant = $makeUser('first');
    $secondApplicant = $makeUser('second');
    $rejected = $makeUser('returning', 'rejected');
    $snapshots = [];
    foreach ($ddl as $table => $sql) {
        $columns = Schema::getColumnListing($table);
        $snapshots[$table] = [$columns, DB::table($table)->select($columns)->get()->map(fn ($r) => serialize($r))->sort()->values()->all()];
    }
    if (! Schema::hasTable('hiring_application_history')) {
        (require database_path('migrations/2026_10_14_000002_add_role_hiring_management.php'))->up();
        foreach ($snapshots as $table => [$columns, $expected]) {
            $check(DB::table($table)->select($columns)->get()->map(fn ($r) => serialize($r))->sort()->values()->all() === $expected, 'Original fixture values changed: '.$table);
        }
        echo 'Additive migration preserved all original fixture columns; legacy hiring/history backfilled.'.PHP_EOL;
    } else {
        DB::table('hiring_identity_locks')->insert(array_map(fn ($id) => ['id' => $id], range(0, 255)));
    }
    $hub = $hub->fresh();
    $settings = fn ($values) => ['op' => 'settings', 'hub' => $hub->id, 'actor' => $admin->id, 'data' => array_merge([
        'role' => 'rider', 'is_hiring' => true, 'limit' => 1, 'closes_at' => null, 'revision' => $hub->fresh()->hiring_revision,
    ], $values)];
    $registration = fn ($suffix) => ['op' => 'register', 'data' => ['account_type' => 'rider', 'is_admin' => false, 'is_logistics' => false, 'business_name' => $hub->company_name,
        'logistics_hub_id' => $hub->id, 'username' => 'audit_'.$suffix, 'email' => $suffix.'@gmail.com', 'given_names' => 'New', 'last_name' => $suffix,
        'middle_name' => null, 'suffix' => null, 'birthday' => '2000-01-01', 'sex' => 'male', 'age' => 26, 'status' => 'pending', 'password' => 'DisposablePassword123',
        'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Test', 'contact_no' => '09123456789', 'vehicle_type' => 'four_wheels', 'vehicle_ownership' => 'own']];
    $race = function (array $a, array $b, bool $secondSucceeds, string $label) use ($name, $server, $check, &$workers) {
        $input = new InputStream;
        $first = new Process([PHP_BINARY, __FILE__, '--worker', $name, base64_encode(json_encode($a)), 'hold'], base_path(), null, $input, 45);
        $second = new Process([PHP_BINARY, __FILE__, '--worker', $name, base64_encode(json_encode($b)), 'run'], base_path(), null, null, 45);
        $workers = [$first, $second];
        $first->start();
        $until = microtime(true) + 20;
        while (! str_contains($first->getOutput(), 'UNCOMMITTED') && $first->isRunning() && microtime(true) < $until) {
            usleep(20000);
        }
        $check(str_contains($first->getOutput(), 'UNCOMMITTED'), $label.' first worker failed: '.$first->getErrorOutput());
        $second->start();
        $waited = false;
        $until = microtime(true) + 20;
        $q = $server->prepare('SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE WHERE l.OBJECT_SCHEMA=?');
        while ($second->isRunning() && microtime(true) < $until) {
            $q->execute([$name]);
            if ((int) $q->fetchColumn()) {
                $waited = true;
                break;
            }
            usleep(20000);
        }
        $check($waited, $label.' did not exercise a real MySQL lock wait: '.$second->getErrorOutput());
        $input->write("commit\n");
        $input->close();
        $first->wait();
        $second->wait();
        $check($first->isSuccessful() && str_contains($first->getOutput(), 'COMMITTED'), $label.' first commit failed.');
        $expected = $secondSucceeds ? 'COMMITTED' : 'REJECTED:422';
        $check($second->isSuccessful() && str_contains($second->getOutput(), $expected), $label.' second result unexpected: '.$second->getOutput().$second->getErrorOutput());
        echo $label.': actual MySQL lock wait; first committed, second '.$expected.'.'.PHP_EOL;
    };
    $operation($settings([]));
    $race(['op' => 'approve', 'id' => $firstApplicant->id], ['op' => 'approve', 'id' => $secondApplicant->id], false, 'Final approved slot');
    $check(app(HubHiring::class)->personnel($hub, 'rider')->count() === 1, 'Capacity exceeded.');
    $operation($settings(['limit' => null]));
    $race($settings(['is_hiring' => false, 'limit' => null]), $registration('late'), false, 'Close during registration');
    $check(! User::where('username', 'audit_late')->exists(), 'Closed hiring admitted stale registration.');
    $operation($settings(['limit' => null]));
    $revision = $hub->fresh()->hiring_revision;
    $race($settings(['limit' => 5, 'revision' => $revision]), $settings(['limit' => 9, 'revision' => $revision]), false, 'Stale settings revision');
    $race($settings(['limit' => 0]), ['op' => 'approve', 'id' => $secondApplicant->id], false, 'Limit reduction during approval');
    $operation($settings(['limit' => null]));
    $race($registration('returning'), $registration('returning'), false, 'Concurrent reapplication');
    $check(User::where('username', 'audit_returning')->value('id') === $rejected->id, 'Rejected account ID changed.');
    $check(DB::table('hiring_application_history')->where('user_id', $rejected->id)->where('status', 'rejected')->exists(), 'Rejection history lost.');
    $operation($settings(['limit' => 2]));
    $race($registration('alpha'), $registration('bravo'), true, 'Concurrent pending applications');
    $check(app(HubHiring::class)->personnel($hub, 'rider')->count() === 1, 'Pending applications consumed approved slots.');
    $otherHub = LogisticsHub::create(['company_name' => $hub->company_name, 'province' => 'Laguna', 'municipality' => 'Pila', 'is_hiring' => true, 'is_operational' => true]);
    $accented = $registration('accented');
    $plain = $registration('plain');
    $accented['data']['last_name'] = 'café';
    $plain['data']['last_name'] = 'cafe';
    $plain['data']['logistics_hub_id'] = $otherHub->id;
    $race($accented, $plain, false, 'Collation-equivalent identities across hubs');
    $documentA = $registration('documenta');
    $documentB = $registration('documentb');
    $documentA['data']['license_number'] = 'SAME-LICENSE';
    $documentB['data']['license_number'] = 'same-license';
    $documentB['data']['logistics_hub_id'] = $otherHub->id;
    $race($documentA, $documentB, false, 'Duplicate license across hubs');
    $plateA = $registration('platea');
    $plateB = $registration('plateb');
    $plateA['data']['plate_number'] = 'SAME-PLATE';
    $plateB['data']['plate_number'] = 'same-plate';
    $plateB['data']['logistics_hub_id'] = $otherHub->id;
    $race($plateA, $plateB, false, 'Duplicate vehicle plate across hubs');
    echo 'All nine contention checks passed. No live records, files, mail, or payments modified.'.PHP_EOL;
} catch (Throwable $e) {
    $failure = $e;
    fwrite(STDERR, $e->getMessage().PHP_EOL);
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop(1);
        }
    }
    DB::disconnect('mysql');
    if ($created) {
        $server->exec('DROP DATABASE `'.$name.'`');
        echo 'Disposable hiring database removed.'.PHP_EOL;
    }
}
exit($failure ? 1 : 0);
