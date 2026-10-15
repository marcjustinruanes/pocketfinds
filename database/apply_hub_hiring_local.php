<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$failure = null;
try {
    if (config('app.env') !== 'local' || config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1') {
        throw new RuntimeException('Only local MySQL may be updated by this helper.');
    }
    if (Schema::hasTable('hiring_application_history')) {
        throw new RuntimeException('Hiring tables already exist; inspect migration status before attempting installation.');
    }
    $pdo = DB::connection()->getPdo();
    $tables = Schema::getTableListing(DB::connection()->getDatabaseName(), false);
    $quoteIdentifier = fn ($name) => '`'.str_replace('`', '``', $name).'`';
    $signature = function ($rows) {
        $values = array_map('serialize', $rows);
        sort($values, SORT_STRING);

        return hash('sha256', serialize($values));
    };
    $stream = fopen('php://temp/maxmemory:5242880', 'w+');
    fwrite($stream, "-- Private local backup before role hiring. Restore into a separate empty database.\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
    DB::beginTransaction();
    try {
        foreach ($tables as $table) {
            fwrite($stream, $pdo->query('SHOW CREATE TABLE '.$quoteIdentifier($table))->fetch(PDO::FETCH_NUM)[1].";\n");
        }
        $snapshots = [];
        $rowCount = 0;
        foreach ($tables as $table) {
            $columns = $pdo->query('SHOW COLUMNS FROM '.$quoteIdentifier($table))->fetchAll(PDO::FETCH_ASSOC);
            $names = implode(',', array_map(fn ($column) => $quoteIdentifier($column['Field']), $columns));
            $rows = $pdo->query('SELECT '.$names.' FROM '.$quoteIdentifier($table))->fetchAll(PDO::FETCH_ASSOC);
            $snapshots[$table] = [$names, $signature($rows)];
            foreach ($rows as $row) {
                $values = array_map(function ($column) use ($row, $pdo) {
                    $value = $row[$column['Field']];

                    return $value === null ? 'NULL' : (preg_match('/blob|binary/i', $column['Type']) && $value !== '' ? '0x'.bin2hex($value) : $pdo->quote((string) $value));
                }, $columns);
                fwrite($stream, 'INSERT INTO '.$quoteIdentifier($table).' ('.$names.') VALUES ('.implode(',', $values).");\n");
                $rowCount++;
            }
        }
        DB::rollBack(); // Read-only snapshot; no operational writes are committed here.
    } catch (Throwable $error) {
        DB::rollBack();
        throw $error;
    }
    fwrite($stream, "SET FOREIGN_KEY_CHECKS=1;\n");
    rewind($stream);
    $backup = 'hiring-before-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
    if (! Storage::disk('local')->writeStream($backup, $stream)) {
        throw new RuntimeException('Private backup could not be stored. Migration was not started.');
    }
    fclose($stream);
    echo 'Private backup: '.Storage::disk('local')->path($backup).PHP_EOL;
    $result = Artisan::call('migrate', ['--path' => 'database/migrations/2026_10_14_000002_add_role_hiring_management.php', '--no-interaction' => true]);
    echo Artisan::output();
    if ($result !== 0) {
        throw new RuntimeException('Migration command failed; inspect its output and the saved backup.');
    }
    foreach ($snapshots as $table => [$names, $expected]) {
        if ($table === 'migrations') {
            continue;
        } // Artisan records this newly applied migration.
        $actual = $signature($pdo->query('SELECT '.$names.' FROM '.$quoteIdentifier($table))->fetchAll(PDO::FETCH_ASSOC));
        if ($actual !== $expected) {
            throw new RuntimeException('Original values changed in '.$table.'; investigate without automatic rollback.');
        }
    }
    echo 'Migration applied without force; original business values preserved across '.(count($snapshots) - 1).' tables / '.$rowCount.' snapshotted rows. No live rollback/reset performed.'.PHP_EOL;
} catch (Throwable $error) {
    $failure = $error;
    fwrite(STDERR, $error->getMessage().PHP_EOL);
}
exit($failure ? 1 : 0);
