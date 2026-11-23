<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
// Reuse utility functions without printing the schema inventory.
$argv=[__FILE__,'C:/Users/Chlouie Cabot/Downloads/Dump20261113.sql'];ob_start();require __DIR__.'/merge-shared-database.php';ob_end_clean();
$tag=date('Ymd_His');$verify='pocketfinds_verify_'.$tag;$original='pocketfinds_verify_web_'.$tag;$appOriginal='pocketfinds_verify_app_'.$tag;
foreach([$verify,$original,$appOriginal] as $db)$pdo->exec('CREATE DATABASE '.qi($db).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$sql=file_get_contents($out.'/Dump20261113.sql');
$sql=preg_replace('/^CREATE DATABASE IF NOT EXISTS `pocketfinds`.*;\r?$/m','',$sql,1,$count);if($count!==1)throw new RuntimeException('Unexpected export database header.');
$sql=preg_replace('/^USE `pocketfinds`;\r?$/m','USE '.qi($verify).';',$sql,1,$count);if($count!==1)throw new RuntimeException('Unexpected export USE statement.');
$testFile=$out.'/roundtrip-test.sql';file_put_contents($testFile,$sql);
mysqlCommand('mysql',['--default-character-set=utf8mb4',$verify],$testFile);
mysqlCommand('mysql',['--default-character-set=utf8mb4',$original],$input);
mysqlCommand('mysql',['--default-character-set=utf8mb4',$appOriginal],$out.'/'.$state['backup']);
$pdo->exec("SET time_zone='+08:00'");
$checks=0;$webRows=0;$appRows=0;
foreach(tables($stage) as $t){$a=(int)$pdo->query('SELECT COUNT(*) FROM '.qi($stage).'.'.qi($t))->fetchColumn();$b=(int)$pdo->query('SELECT COUNT(*) FROM '.qi($verify).'.'.qi($t))->fetchColumn();if($a!==$b)throw new RuntimeException('Roundtrip row count mismatch: '.$t);$checks++;}
foreach(tables($original) as $t){
 $rows=$pdo->query('SELECT * FROM '.qi($original).'.'.qi($t))->fetchAll(PDO::FETCH_ASSOC);$metadata=columns($original,$t);
 foreach($rows as $row){$cols=array_keys($row);$stmt=$pdo->prepare('SELECT COUNT(*) FROM '.qi($verify).'.'.qi($t).' WHERE '.implode(' AND ',array_map(fn($c)=>qi($c).' <=> '.($metadata[$c]['Type']==='json'?'CAST(? AS JSON)':'?'),$cols)));$stmt->execute(array_values($row));if(!(int)$stmt->fetchColumn())throw new RuntimeException('Original website row changed or missing: '.$t);$webRows++;}
}
foreach(tables($appOriginal) as $t){
 $rows=$pdo->query('SELECT * FROM '.qi($appOriginal).'.'.qi($t))->fetchAll(PDO::FETCH_ASSOC);$archive=$pdo->prepare('SELECT original_row FROM '.qi($verify).'.integration_app_archive WHERE source_table=?');$archive->execute([$t]);$hashes=[];
 foreach($archive->fetchAll(PDO::FETCH_COLUMN) as $json){$v=json_decode($json,true,512,JSON_THROW_ON_ERROR);foreach($v as &$field)if(is_array($field)&&($field['__pocketfinds_encoding']??null)==='base64')$field=base64_decode($field['data'],true);unset($field);ksort($v);$hashes[hash('sha256',serialize($v))]=true;}
 foreach($rows as $row){ksort($row);if(!isset($hashes[hash('sha256',serialize($row))]))throw new RuntimeException('Original app row not preserved in archive: '.$t);$appRows++;}
}
$identityMismatch=(int)$pdo->query('SELECT COUNT(*) FROM '.qi($verify).".integration_app_archive a WHERE a.source_table='users' AND NOT EXISTS (SELECT 1 FROM ".qi($verify).".users u WHERE u.email=JSON_UNQUOTE(JSON_EXTRACT(a.original_row,'$.email')))")->fetchColumn();
if($identityMismatch)throw new RuntimeException('An app email identity was not preserved as an active account.');
$report=['roundtrip_table_checks'=>$checks,'original_website_rows_preserved'=>$webRows,'original_app_rows_archived'=>$appRows,'app_email_identities_preserved'=>true,'database_name'=>'pocketfinds','export_sha256'=>hash_file('sha256',$out.'/Dump20261113.sql')];
file_put_contents($out.'/roundtrip-validation.json',json_encode($report,JSON_PRETTY_PRINT));echo json_encode($report,JSON_PRETTY_PRINT).PHP_EOL;
// Remove only disposable verification copies created in this invocation.
foreach([$verify,$original,$appOriginal] as $db){if(!str_starts_with($db,'pocketfinds_verify_')||$db===$live)throw new RuntimeException('Unsafe verification cleanup target.');$pdo->exec('DROP DATABASE '.qi($db));}
