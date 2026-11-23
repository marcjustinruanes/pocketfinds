<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/api/bootstrap.php';
use Illuminate\Support\Facades\DB;
$cfg=config('database.connections.mysql');
if($cfg['database']!=='pocketfinds')throw new RuntimeException('Expected pocketfinds database.');
if(in_array('--export-only',$argv,true)){dumpWithoutSecrets(dirname(__DIR__).'/database-export/Dump20261113.sql',$cfg);echo "Updated database-export/Dump20261113.sql\n";exit;}
$columns=DB::select("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND LOWER(COLUMN_NAME) LIKE '%coin%'");
$tables=DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME) LIKE '%coin%'");
$enum=DB::select("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND DATA_TYPE='enum' AND LOWER(COLUMN_TYPE) LIKE '%coin%'");
$plan=['tables'=>$tables,'columns'=>$columns,'enums'=>$enum];
if(!in_array('--apply',$argv,true)){echo json_encode($plan,JSON_PRETTY_PRINT).PHP_EOL;exit;}
$root=dirname(__DIR__);$dir=$root.'/database-export';if(!is_dir($dir))mkdir($dir,0700,true);
$stamp=date('Ymd-His');$backup=$dir.'/pocketfinds-before-remove-coins-'.$stamp.'.sql';
function dumpWithoutSecrets(string $path,array $cfg):void{
 putenv('MYSQL_PWD='.$cfg['password']);
 try{
  $args=['C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe','--host='.$cfg['host'],'--port='.$cfg['port'],'--user='.$cfg['username'],'--single-transaction','--no-tablespaces','--set-gtid-purged=OFF','--column-statistics=0','--hex-blob','--default-character-set=utf8mb4','--complete-insert','--databases','pocketfinds','--result-file='.$path];
  $process=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if(!is_resource($process))throw new RuntimeException('Could not start database backup.');
  fclose($pipes[0]);stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
  if(proc_close($process)!==0 || !is_file($path) || filesize($path)<1000)throw new RuntimeException('Database backup failed: '.$error);
 }finally{putenv('MYSQL_PWD');}
}
function identifier(string $name):string{return '`'.str_replace('`','``',$name).'`';}
$flag=$root.'/api/storage/database-maintenance.flag';
if(is_file($flag))throw new RuntimeException('Another database update is already running.');
file_put_contents($flag,'Removing retired reward schema');
try {
dumpWithoutSecrets($backup,$cfg);
$totals=DB::table('orders')->select('id','total')->orderBy('id')->get()->toJson();
// Fold historical discounts into the ordinary discount field before removing
// their obsolete breakdown. Never change an existing order's payable total.
if(Illuminate\Support\Facades\Schema::hasColumn('orders','coin_discount_amount'))DB::statement('UPDATE orders SET discount_amount=COALESCE(discount_amount,0)+COALESCE(coin_discount_amount,0) WHERE COALESCE(coin_discount_amount,0)<>0');
foreach($enum as $column){
 $table=identifier($column->TABLE_NAME);$field=identifier($column->COLUMN_NAME);
 preg_match_all("/'((?:[^']|'')*)'/",$column->COLUMN_TYPE,$matches);
 $remaining=array_values(array_filter($matches[1],fn($value)=>stripos($value,'coin')===false));
 if(!$remaining)throw new RuntimeException('No remaining values for '.$column->TABLE_NAME.'.'.$column->COLUMN_NAME);
 foreach($matches[1] as $value)if(stripos($value,'coin')!==false){
  $fallback=in_array('discount',$remaining,true)?'discount':$remaining[0];
  $updates=[$column->COLUMN_NAME=>$fallback];
  if(Illuminate\Support\Facades\Schema::hasColumn($column->TABLE_NAME,'is_active'))$updates['is_active']=false;
  if(Illuminate\Support\Facades\Schema::hasColumn($column->TABLE_NAME,'discount_amount'))$updates['discount_amount']=0;
  DB::table($column->TABLE_NAME)->where($column->COLUMN_NAME,$value)->update($updates);
 }
 $pdo=DB::connection()->getPdo();$values=implode(',',array_map(fn($value)=>$pdo->quote($value),$remaining));
 $default=$column->COLUMN_DEFAULT===null?null:(in_array($column->COLUMN_DEFAULT,$remaining,true)?$column->COLUMN_DEFAULT:$remaining[0]);
 DB::statement("ALTER TABLE $table MODIFY COLUMN $field ENUM($values) ".($column->IS_NULLABLE==='YES'?'NULL':'NOT NULL').($default===null?($column->IS_NULLABLE==='YES'?' DEFAULT NULL':''):' DEFAULT '.$pdo->quote($default)));
}
foreach($columns as $column)DB::statement('ALTER TABLE '.identifier($column->TABLE_NAME).' DROP COLUMN '.identifier($column->COLUMN_NAME));
foreach($tables as $table)DB::statement('DROP TABLE '.identifier($table->TABLE_NAME));
if($totals!==DB::table('orders')->select('id','total')->orderBy('id')->get()->toJson())throw new RuntimeException('Order payable totals changed unexpectedly. Restore the backup before proceeding.');
$left=DB::select("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (LOWER(COLUMN_NAME) LIKE '%coin%' OR LOWER(TABLE_NAME) LIKE '%coin%' OR (DATA_TYPE='enum' AND LOWER(COLUMN_TYPE) LIKE '%coin%'))");
if($left)throw new RuntimeException('Some coin schema remains.');
$export=$dir.'/Dump20261113.sql';
if(is_file($export))copy($export,$dir.'/Dump20261113-before-remove-coins-'.$stamp.'.sql');
dumpWithoutSecrets($export,$cfg);
$report=['database'=>'pocketfinds','backup'=>$backup,'removed'=>$plan,'order_totals_preserved'=>true,'remaining_coin_schema'=>0,'export'=>$export,'export_sha256'=>hash_file('sha256',$export)];
file_put_contents($dir.'/remove-coins-'.$stamp.'.json',json_encode($report,JSON_PRETTY_PRINT));
echo json_encode($report,JSON_PRETTY_PRINT).PHP_EOL;

}finally{if(is_file($flag))unlink($flag);}
