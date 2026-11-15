<?php
/** Build an isolated, reviewable web/app database merge; never replace the live database. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/api/bootstrap.php';
$root=dirname(__DIR__);$out=$root.'/database-export';if(!is_dir($out))mkdir($out,0700,true);
$cfg=config('database.connections.mysql');$live=$cfg['database'];
if($live!=='pocketfinds')throw new RuntimeException('Expected the live database to be pocketfinds.');
$input=$argv[1]??'C:/Users/Chlouie Cabot/Downloads/Dump20261113.sql';
if(!is_file($input))throw new RuntimeException('Website dump not found.');
$bin='C:/Program Files/MySQL/MySQL Server 8.0/bin/';
function mysqlCommand(string $tool,array $args,?string $input=null):void{
 global $cfg,$bin;
 putenv('MYSQL_PWD='.$cfg['password']);
 $descriptors=[0=>$input?['file',$input,'r']:['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
 $p=proc_open(array_merge([$bin.$tool.'.exe','--host='.$cfg['host'],'--port='.$cfg['port'],'--user='.$cfg['username']],$args),$descriptors,$pipes);
 if(!is_resource($p))throw new RuntimeException('Unable to start MySQL utility.');
 if(!$input)fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);putenv('MYSQL_PWD');
 if($code)throw new RuntimeException($tool.' failed: '.$error);
}
$pdo=Illuminate\Support\Facades\DB::connection()->getPdo();
function qi(string $name):string{return '`'.str_replace('`','``',$name).'`';}
function tables(string $db):array{global $pdo;return $pdo->query('SHOW FULL TABLES FROM '.qi($db)." WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);}
function columns(string $db,string $table):array{global $pdo;return $pdo->query('SHOW FULL COLUMNS FROM '.qi($db).'.'.qi($table))->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);}
function indexes(string $db,string $table):array{global $pdo;$result=[];foreach($pdo->query('SHOW INDEX FROM '.qi($db).'.'.qi($table))->fetchAll(PDO::FETCH_ASSOC) as $index){if(!$index['Non_unique'])$result[$index['Key_name']][(int)$index['Seq_in_index']]=$index['Column_name'];}foreach($result as &$cols){ksort($cols);$cols=array_values($cols);}return $result;}
$stateFile=$out.'/merge-state.json';
if(!is_file($stateFile)||in_array('--rebuild',$argv,true)){
 $stage='pocketfinds_merge_'.date('Ymd_His');
 $backup=$out.'/pocketfinds-app-before-merge-'.date('Ymd-His').'.sql';
 mysqlCommand('mysqldump',['--single-transaction','--no-tablespaces','--hex-blob','--result-file='.$backup,$live]);
 $pdo->exec('CREATE DATABASE '.qi($stage).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
 mysqlCommand('mysql',['--default-character-set=utf8mb4',$stage],$input);
 file_put_contents($stateFile,json_encode(['stage'=>$stage,'live'=>$live,'input_sha256'=>hash_file('sha256',$input),'backup'=>basename($backup),'phase'=>'imported'],JSON_PRETTY_PRINT));
 echo "Website dump imported into an isolated staging database. Live database backed up.\n";
}
$state=json_decode(file_get_contents($stateFile),true,512,JSON_THROW_ON_ERROR);$stage=$state['stage'];
if($state['input_sha256']!==hash_file('sha256',$input))throw new RuntimeException('The input dump changed; use a new export directory.');
$appTables=tables($live);$webTables=tables($stage);$report=['database_name'=>$live,'website_tables'=>count($webTables),'app_tables'=>count($appTables),'app_only_tables'=>array_values(array_diff($appTables,$webTables)),'missing_columns'=>[],'different_columns'=>[],'unique_indexes'=>[]];
foreach(array_intersect($appTables,$webTables) as $table){
 $appCols=columns($live,$table);$webCols=columns($stage,$table);
 foreach($appCols as $name=>$col){if(!isset($webCols[$name]))$report['missing_columns'][$table][]=$name;elseif($col['Type']!==$webCols[$name]['Type'])$report['different_columns'][$table][$name]=['app'=>$col['Type'],'web'=>$webCols[$name]['Type']];}
 $report['unique_indexes'][$table]=indexes($stage,$table);
}
if($state['phase']==='imported')file_put_contents($out.'/schema-comparison.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode(array_diff_key($report,['unique_indexes'=>true]),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
if(in_array('--merge',$argv,true)){
 if($state['phase']!=='imported')throw new RuntimeException('This staging merge was already completed.');
 require __DIR__.'/SharedDatabaseMerge.php';
 $merge=new SharedDatabaseMerge($pdo,$live,$stage);$merge->schema();$merge->merge();
 file_put_contents($out.'/merge-report.json',json_encode($merge->report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
 $state['phase']='merged';file_put_contents($stateFile,json_encode($state,JSON_PRETTY_PRINT));
 echo "Merged app records into staging; original app rows retained in integration_app_archive.\n";
}
if(in_array('--export',$argv,true)){
 if($state['phase']!=='merged')throw new RuntimeException('Complete the staging merge before exporting.');
 $sql=$out.'/merged-data.sql';
 mysqlCommand('mysqldump',['--single-transaction','--no-tablespaces','--set-gtid-purged=OFF','--column-statistics=0','--default-character-set=utf8mb4','--hex-blob','--complete-insert','--result-file='.$sql,$stage]);
 $header="-- PocketFinds website + Flutter app shared database\n-- Original website dump: Dump20261113.sql\n-- Database name remains pocketfinds. Back up your server before import.\n-- Original app rows and conflicting versions are retained in integration_app_archive.\nCREATE DATABASE IF NOT EXISTS `pocketfinds` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nUSE `pocketfinds`;\n\n";
 file_put_contents($out.'/Dump20261113.sql',$header.file_get_contents($sql));
 echo "Exported database-export/Dump20261113.sql with database name pocketfinds.\n";
}
