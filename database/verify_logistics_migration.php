<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('app.env') !== 'local' || config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1') {
    throw new RuntimeException('Local MySQL required.');
}
$live = DB::connection()->getPdo();
$serverConfig = config('database.connections.mysql');
$serverConfig['database'] = null;
$serverConfig['url'] = null;
config(['database.connections.logistics_migration_audit' => $serverConfig]);
$server = DB::connection('logistics_migration_audit')->getPdo();
$name = 'pocketfinds_logistics_audit_'.bin2hex(random_bytes(6));
$created = false;
$default = config('database.default');
$signature = function (array $rows): string {
    $values = array_map('serialize', $rows);
    sort($values, SORT_STRING);

    return hash('sha256', serialize($values));
};
try {
    $server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    DB::purge('logistics_migration_audit');
    $serverConfig['database'] = $name;
    config(['database.connections.logistics_migration_audit' => $serverConfig, 'database.default' => 'logistics_migration_audit']);
    $audit = DB::connection()->getPdo();
    preg_match_all('/^CREATE TABLE `([^`]+)` \(.*?^\) ENGINE=[^\r\n]+;/ms', file_get_contents(base_path('Dump20261009.sql')), $matches, PREG_SET_ORDER);
    if (count($matches) !== 46) {
        throw new RuntimeException('Unexpected source schema table count.');
    }
    $audit->exec('SET FOREIGN_KEY_CHECKS=0');
    $snapshots = [];
    $rowsCopied = 0;
    foreach ($matches as $match) {
        $audit->exec($match[0]);
    }
    foreach ($matches as $match) {
        $table = $match[1];
        $columns = $audit->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
        $names = implode(',', array_map(fn ($c) => '`'.$c['Field'].'`', $columns));
        $rows = $live->query('SELECT '.$names.' FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $value = $row[$column['Field']];
                $values[] = $value === null ? 'NULL' : (str_contains($column['Type'], 'blob') ? ($value === '' ? "''" : '0x'.bin2hex($value)) : $audit->quote((string) $value));
            }
            $audit->exec('INSERT INTO `'.$table.'` ('.$names.') VALUES ('.implode(',', $values).')');
            $rowsCopied++;
        }
        $snapshots[$table] = ['columns' => $names, 'hash' => $signature($rows)];
    }
    $audit->exec('SET FOREIGN_KEY_CHECKS=1');
    $migration = require database_path('migrations/2026_10_13_000001_add_logistics_operations.php');
    $migration->up();
    foreach ($snapshots as $table => $expected) {
        if ($signature($audit->query('SELECT '.$expected['columns'].' FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC)) !== $expected['hash']) {
            throw new RuntimeException('Migration changed original values in '.$table);
        }
    }
    echo 'MySQL migration up: preserved all original values across '.count($snapshots).' tables and '.$rowsCopied.' rows.'.PHP_EOL;
    $migration->down();
    foreach ($snapshots as $table => $expected) {
        if ($signature($audit->query('SELECT '.$expected['columns'].' FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC)) !== $expected['hash']) {
            throw new RuntimeException('Rollback changed original values in '.$table);
        }
    }
    echo 'MySQL migration down: original values preserved in the disposable database.'.PHP_EOL;
} finally {
    config(['database.default' => $default]);
    DB::purge('logistics_migration_audit');
    if ($created && preg_match('/^pocketfinds_logistics_audit_[0-9a-f]{12}$/D', $name)) {
        $server->exec('DROP DATABASE `'.$name.'`');
        echo 'Disposable audit database removed; live schema was not modified.'.PHP_EOL;
    }
}
