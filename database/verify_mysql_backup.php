<?php

// Prove the private SQL backup restores without modifying the live database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$pdo=DB::connection()->getPdo();
$backup=storage_path('app/private/mysql-migration/mysql-backup.sql');
if (hash_file('sha256',$backup)!==trim(file_get_contents($backup.'.sha256'))) throw new RuntimeException('Backup checksum differs');
$scratch='pocketfinds_restore_check_'.bin2hex(random_bytes(6));
$created=false;
try {
    $pdo->exec('CREATE DATABASE `'.$scratch.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created=true;
    $pdo->exec('USE `'.$scratch.'`');
    $pdo->exec(file_get_contents($backup));
    $tables=$pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
    $rows=0;
    foreach ($tables as $table) {
        $key=in_array($table,['cache','cache_locks']) ? 'key' : 'id';
        $original=$pdo->query('SELECT * FROM `pocketfinds`.`'.$table.'` ORDER BY `'.$key.'`')->fetchAll(PDO::FETCH_ASSOC);
        $restored=$pdo->query('SELECT * FROM `'.$scratch.'`.`'.$table.'` ORDER BY `'.$key.'`')->fetchAll(PDO::FETCH_ASSOC);
        if ($original!==$restored) throw new RuntimeException('Restored rows differ in '.$table);
        $rows+=count($restored);
    }
    file_put_contents(storage_path('app/private/mysql-migration/backup-verified.json'),json_encode(['verified_at'=>gmdate('c'),'tables'=>count($tables),'rows'=>$rows,'sha256'=>hash_file('sha256',$backup)],JSON_PRETTY_PRINT));
    echo 'SQL backup restored and all values verified: '.count($tables).' tables / '.$rows.' rows.'.PHP_EOL;
} finally {
    $pdo->exec('USE `pocketfinds`');
    if ($created && str_starts_with($scratch,'pocketfinds_restore_check_') && $scratch!=='pocketfinds') $pdo->exec('DROP DATABASE `'.$scratch.'`');
}
