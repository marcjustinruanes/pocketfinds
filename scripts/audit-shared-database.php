<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/api/bootstrap.php';
$out=dirname(__DIR__).'/database-export';$state=json_decode(file_get_contents($out.'/merge-state.json'),true,512,JSON_THROW_ON_ERROR);
$pdo=Illuminate\Support\Facades\DB::connection()->getPdo();$stage=in_array('--live',$argv,true)?$state['live']:$state['stage'];
function aq(string $s):string{return '`'.str_replace('`','``',$s).'`';}
$stmt=$pdo->prepare('SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION');$stmt->execute([$stage]);$groups=[];
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$groups[$r['TABLE_NAME'].'.'.$r['CONSTRAINT_NAME']][]=$r;
$bad=[];
foreach($groups as $name=>$refs){$joins=[];$nonnull=[];foreach($refs as $r){$joins[]='p.'.aq($r['REFERENCED_COLUMN_NAME']).'=c.'.aq($r['COLUMN_NAME']);$nonnull[]='c.'.aq($r['COLUMN_NAME']).' IS NOT NULL';}
 $r=$refs[0];$sql='SELECT COUNT(*) FROM '.aq($stage).'.'.aq($r['TABLE_NAME']).' c LEFT JOIN '.aq($stage).'.'.aq($r['REFERENCED_TABLE_NAME']).' p ON '.implode(' AND ',$joins).' WHERE '.implode(' AND ',$nonnull).' AND p.'.aq($r['REFERENCED_COLUMN_NAME']).' IS NULL';$count=(int)$pdo->query($sql)->fetchColumn();if($count)$bad[$name]=$count;
}
$counts=[];foreach($pdo->query('SHOW FULL TABLES FROM '.aq($stage)." WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $t)$counts[$t]=(int)$pdo->query('SELECT COUNT(*) FROM '.aq($stage).'.'.aq($t))->fetchColumn();
$result=['stage'=>$stage,'tables'=>count($counts),'rows'=>array_sum($counts),'foreign_keys_checked'=>count($groups),'orphaned_foreign_keys'=>$bad,'row_counts'=>$counts];
$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_SCHEMA IS NOT NULL AND REFERENCED_TABLE_SCHEMA<>?');$stmt->execute([$stage,$stage]);$result['cross_database_foreign_keys']=(int)$stmt->fetchColumn();
file_put_contents($out.(in_array('--live',$argv,true)?'/live-validation-report.json':'/validation-report.json'),json_encode($result,JSON_PRETTY_PRINT));
echo json_encode(array_diff_key($result,['row_counts'=>true]),JSON_PRETTY_PRINT).PHP_EOL;
if($bad||$result['cross_database_foreign_keys'])exit(1);
