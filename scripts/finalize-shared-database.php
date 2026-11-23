<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/api/bootstrap.php';
$out=dirname(__DIR__).'/database-export';$state=json_decode(file_get_contents($out.'/merge-state.json'),true,512,JSON_THROW_ON_ERROR);$stage=$state['stage'];$live=$state['live'];
$pdo=Illuminate\Support\Facades\DB::connection()->getPdo();
function fq(string $s):string{return '`'.str_replace('`','``',$s).'`';}
$stmt=$pdo->prepare('SELECT TABLE_NAME,INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SEQ_IN_INDEX,SUB_PART,COLLATION,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX');
$stmt->execute([$live]);$source=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$source[$r['TABLE_NAME']][$r['INDEX_NAME']][]=$r;
$stmt->execute([$stage]);$existing=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$existing[$r['TABLE_NAME']][$r['INDEX_NAME']][]=$r;
$added=0;
foreach($source as $table=>$indexes)foreach($indexes as $name=>$rows){if($name==='PRIMARY')continue;$cols=array_column($rows,'COLUMN_NAME');$unique=!(bool)$rows[0]['NON_UNIQUE'];$covered=false;
 foreach($existing[$table]??[] as $present){if(array_column($present,'COLUMN_NAME')===$cols && (!$unique || !$present[0]['NON_UNIQUE'])){$covered=true;break;}}
 if($covered)continue;
 $definition=[];foreach($rows as $r){if(!$r['COLUMN_NAME'])throw new RuntimeException('Review a functional index before exporting.');$definition[]=fq($r['COLUMN_NAME']).($r['SUB_PART']?'('.(int)$r['SUB_PART'].')':'').($r['COLLATION']==='D'?' DESC':'');}
 $indexName=isset($existing[$table][$name])?'app_'.substr(hash('sha256',$table.$name),0,20):$name;
 $sql='ALTER TABLE '.fq($stage).'.'.fq($table).' ADD '.($unique?'UNIQUE ':'').'INDEX '.fq($indexName).' ('.implode(',',$definition).')';
 $pdo->exec($sql);$added++;
}
$pdo->exec('CREATE TABLE IF NOT EXISTS '.fq($stage).'.integration_shared_database_settings (`name` varchar(100) PRIMARY KEY,`value` text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$insert=$pdo->prepare('REPLACE INTO '.fq($stage).'.integration_shared_database_settings (`name`,`value`) VALUES (?,?)');
foreach(['database_name'=>'pocketfinds','timezone'=>'Asia/Manila','connection_timezone'=>'+08:00','source_dump_sha256'=>$state['input_sha256'],'app_time_conversion'=>'Legacy app datetime values shifted from UTC to Asia/Manila; originals archived.','requires_fresh_login'=>'1'] as $key=>$value)$insert->execute([$key,$value]);
echo "Shared database marker installed; $added app indexes added.\n";
