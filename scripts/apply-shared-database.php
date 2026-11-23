<?php
/** Apply only the verified local merge requested by the user, with a fresh backup. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$argv=[__FILE__,'C:/Users/Chlouie Cabot/Downloads/Dump20261113.sql'];ob_start();require __DIR__.'/merge-shared-database.php';ob_end_clean();
$validation=json_decode(file_get_contents($out.'/roundtrip-validation.json'),true,512,JSON_THROW_ON_ERROR);
if(($validation['export_sha256']??'')!==hash_file('sha256',$out.'/Dump20261113.sql'))throw new RuntimeException('The export does not match the verified SQL.');
if($live!=='pocketfinds' || !in_array($cfg['host'],['127.0.0.1','localhost'],true))throw new RuntimeException('This installer is restricted to the local pocketfinds database.');
$flag=dirname(__DIR__).'/api/storage/database-maintenance.flag';
file_put_contents($flag,'Local shared database installation '.date(DATE_ATOM));
try{
 // Refuse to discard any app edits made since the export snapshot.
 $pdo->exec("SET time_zone='+08:00'");
 foreach(tables($live) as $t){
   $hashes=[];$s=$pdo->prepare('SELECT original_row FROM '.qi($stage).'.integration_app_archive WHERE source_table=?');$s->execute([$t]);
   foreach($s->fetchAll(PDO::FETCH_COLUMN) as $json){$row=json_decode($json,true,512,JSON_THROW_ON_ERROR);foreach($row as &$v)if(is_array($v)&&($v['__pocketfinds_encoding']??null)==='base64')$v=base64_decode($v['data'],true);unset($v);ksort($row);$key=hash('sha256',serialize($row));$hashes[$key]=($hashes[$key]??0)+1;}
   foreach($pdo->query('SELECT * FROM '.qi($live).'.'.qi($t))->fetchAll(PDO::FETCH_ASSOC) as $row){ksort($row);$key=hash('sha256',serialize($row));if(empty($hashes[$key]))throw new RuntimeException('App records changed after export in '.$t.'. Rebuild the merge before importing.');$hashes[$key]--;}
   if(array_sum($hashes)!==0)throw new RuntimeException('App records changed after export in '.$t.'. Rebuild the merge before importing.');
 }
 $backup=$out.'/pocketfinds-before-shared-install-'.date('Ymd-His').'.sql';
 mysqlCommand('mysqldump',['--single-transaction','--no-tablespaces','--hex-blob','--result-file='.$backup,$live]);
 mysqlCommand('mysql',['--default-character-set=utf8mb4',$live],$out.'/Dump20261113.sql');
 foreach(tables($stage) as $t){$expected=(int)$pdo->query('SELECT COUNT(*) FROM '.qi($stage).'.'.qi($t))->fetchColumn();$actual=(int)$pdo->query('SELECT COUNT(*) FROM '.qi($live).'.'.qi($t))->fetchColumn();if($actual!==$expected)throw new RuntimeException('Post-install row count mismatch: '.$t);}
 file_put_contents($out.'/installation.json',json_encode(['database'=>$live,'installed_at'=>date(DATE_ATOM),'backup'=>basename($backup),'export_sha256'=>$validation['export_sha256']],JSON_PRETTY_PRINT));
 echo "Verified shared database installed locally as pocketfinds. Pre-install backup retained.\n";
}finally{if(is_file($flag))unlink($flag);}
