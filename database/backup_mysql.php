<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$pdo=DB::connection()->getPdo();
if (DB::connection()->getDriverName()!=='mysql') throw new RuntimeException('MySQL required');
$file=storage_path('app/private/mysql-migration/mysql-backup.sql');
$out=fopen($file,'w');
$quote=fn ($v)=>$v===null ? 'NULL' : $pdo->quote((string)$v);
fwrite($out,"-- Private PocketFinds MySQL backup; contains account records.\n-- Restore into an EMPTY database selected before executing this file.\nSET NAMES utf8mb4;\nSET @OLD_SQL_MODE=@@SQL_MODE;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET @OLD_FK=@@FOREIGN_KEY_CHECKS;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
$pdo->beginTransaction();
$tables=$pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
$rows=0;
foreach ($tables as $table) {
    $identifier='`'.str_replace('`','``',$table).'`';
    $ddl=$pdo->query('SHOW CREATE TABLE '.$identifier)->fetch(PDO::FETCH_NUM)[1];
    fwrite($out,$ddl.";\n");
    foreach ($pdo->query('SELECT * FROM '.$identifier)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns=implode(',',array_map(fn ($key)=>'`'.str_replace('`','``',$key).'`',array_keys($row)));
        fwrite($out,'INSERT INTO '.$identifier.' ('.$columns.') VALUES ('.implode(',',array_map($quote,array_values($row))).");\n");
        $rows++;
    }
    fwrite($out,"\n");
}
$pdo->commit();
fwrite($out,"SET FOREIGN_KEY_CHECKS=@OLD_FK;\nSET SQL_MODE=@OLD_SQL_MODE;\n");
fclose($out);
file_put_contents($file.'.sha256',hash_file('sha256',$file));
echo 'Saved private SQL backup: '.count($tables).' tables / '.$rows.' rows.'.PHP_EOL;
