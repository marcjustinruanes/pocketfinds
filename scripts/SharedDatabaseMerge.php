<?php
declare(strict_types=1);

/** Offline staging merge. Website records stay authoritative; original app rows are archived. */
final class SharedDatabaseMerge {
    private array $maps=[], $app=[], $web=[], $pk=[], $cols=[], $keys=[], $foreign=[], $used=[], $max=[], $inserted=[], $originalColumns=[];
    public array $report=['inserted'=>[],'linked'=>[],'remapped'=>[],'archived_app_rows'=>0,'conflicts'=>[],'quarantined'=>[]];
    private const USERS=['user_id','buyer_id','seller_id','rider_id','courier_id','pickup_rider_id','hub_transfer_rider_id','actor_id','reviewed_by','approved_by','released_by','created_by','updated_by','requested_by','prepared_by','verified_by','paid_by','reported_by','respondent_id','sender_id','receiver_id','blocker_id','blocked_id','app_buyer_id','app_seller_id'];
    public function __construct(private PDO $pdo,private string $source,private string $target){}
    private function table(string $db,string $t):string{return qi($db).'.'.qi($t);}
    private function query(string $sql,array $args=[]):array{$s=$this->pdo->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);}
    private function uid(string $table,mixed $old):mixed{return $old===null?null:($this->maps[$table][(string)$old]??$old);}
    private function newId(string $table,mixed $old):mixed{
        if(is_numeric($old))return ++$this->max[$table];
        return Illuminate\Support\Str::uuid()->toString();
    }
    private function ref(string $table,string $col):?string{
        if(isset($this->foreign[$table][$col]))return $this->foreign[$table][$col];
        if(in_array($col,self::USERS,true))return 'users';
        return match($col){'order_id','collection_order_id'=>'orders','shipment_id','collection_shipment_id'=>'shipments','product_id','product_reference','gift_product_id'=>'products','category_id'=>'categories','payment_method_id'=>'payment_methods','delivery_address_id','address_id'=>'buyer_addresses','purchase_id','gcash_purchase_id'=>'gcash_purchases','transfer_batch_id','batch_id'=>str_starts_with($table,'hub_transfer')?'hub_transfer_batches':null,'leg_id'=>'shipment_hub_legs','message_id'=>'messages','review_id'=>'reviews','hub_id','logistics_hub_id','origin_hub_id','destination_hub_id'=>'logistics_hubs','vehicle_id'=>'company_vehicles','destination_id'=>$table==='logistics_rider_payouts'?'logistics_rider_destinations':'logistics_payout_destinations','compensation_id'=>'logistics_compensations','voucher_id'=>str_starts_with($table,'mobile_commerce_')?'mobile_commerce_vouchers':(str_starts_with($table,'commerce_')||$table==='delivery_late_vouchers'?'commerce_vouchers':'vouchers'),'flash_sale_id'=>str_starts_with($table,'mobile_commerce_')?'mobile_commerce_flash_sales':'commerce_flash_sales',default=>null};
    }
    private function jsonValue(mixed $v,?string $key=null):mixed{
        if(is_array($v)){foreach($v as $k=>&$value)$value=$this->jsonValue($value,is_string($k)?$k:null);unset($value);return $v;}
        if($key && ($ref=$this->ref('', $key)))return $this->uid($ref,$v);
        if(is_string($v) && preg_match('/^[a-f0-9-]{36}$/i',$v)){foreach($this->maps as $map)if(isset($map[$v]) && $map[$v]!==$v)return $map[$v];}
        return $v;
    }
    private function transform(string $table,array $row,bool $ownId=true):array{
        foreach($row as $col=>&$v){
            if($ownId && $this->pk[$table]===[$col] && !$this->ref($table,$col)){$v=$this->uid($table,$v);continue;}
            if($v===null)continue;
            if($col==='scope' && is_string($v) && preg_match('/^(.+):(\d+)$/D',$v,$parts)){$v=$parts[1].':'.$this->uid('users',$parts[2]);continue;}
            $ref=$this->ref($table,$col);
            if($col==='tokenable_id' && str_ends_with($row['tokenable_type']??'','\\User'))$ref='users';
            if($ref){$v=$this->uid($ref,$v);continue;}
            $type=$this->cols[$table][$col]['Type']??'';
            if(preg_match('/^(timestamp|datetime)/i',$type) && $v!=='0000-00-00 00:00:00')$v=(new DateTimeImmutable((string)$v,new DateTimeZone('UTC')))->modify('+8 hours')->format(str_contains($type,'(6)')?'Y-m-d H:i:s.u':'Y-m-d H:i:s');
            elseif($type==='json'){
                $decoded=json_decode((string)$v,true);if(json_last_error()===JSON_ERROR_NONE)$v=json_encode($this->jsonValue($decoded),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
            }
            elseif(is_string($v) && preg_match('/^[a-f0-9-]{36}$/i',$v))$v=$this->jsonValue($v);
        }unset($v);
        return $row;
    }
    private function matches(string $table,array $row):?array{
        $candidates=[];
        foreach($this->keys[$table] as $key){
            if($key===($this->pk[$table]??[]) && count($key)===1)continue;
            $args=[];$clauses=[];
            foreach($key as $col){if(!array_key_exists($col,$row)||$row[$col]===null||$row[$col]==='')continue 2;$clauses[]=qi($col).'=?';$args[]=$row[$col];}
            if(!$clauses)continue;
            foreach($this->query('SELECT * FROM '.$this->table($this->target,$table).' WHERE '.implode(' AND ',$clauses).' LIMIT 1',$args) as $found)$candidates[json_encode(array_intersect_key($found,array_flip($this->pk[$table])))]=$found;
        }
        if(count($candidates)>1)throw new RuntimeException("Conflicting unique identities in $table; manual review required.");
        return $candidates?array_values($candidates)[0]:null;
    }
    public function schema():void{
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach(tables($this->source) as $t){
            if(!in_array($t,tables($this->target),true)){
                $ddl=$this->pdo->query('SHOW CREATE TABLE '.$this->table($this->source,$t))->fetch(PDO::FETCH_NUM)[1];
                $ddl=preg_replace('/^CREATE TABLE `[^`]+`/','CREATE TABLE '.$this->table($this->target,$t),$ddl);
                $this->pdo->exec($ddl);
            }
            $sourceCols=columns($this->source,$t);$targetCols=columns($this->target,$t);$this->originalColumns[$t]=array_keys($targetCols);
            $ddl=$this->pdo->query('SHOW CREATE TABLE '.$this->table($this->source,$t))->fetch(PDO::FETCH_NUM)[1];
            foreach($sourceCols as $col=>$meta){
                if(!isset($targetCols[$col])){
                    if(!preg_match('/^  '.preg_quote(qi($col),'/').' (.+?)(?:,)?$/m',$ddl,$match))throw new RuntimeException("Cannot extract $t.$col definition.");
                    $this->pdo->exec('ALTER TABLE '.$this->table($this->target,$t).' ADD COLUMN '.qi($col).' '.rtrim($match[1],','));
                }else{
                    $dst=$targetCols[$col];$type=$dst['Type'];$change=false;
                    if(str_starts_with($type,'enum(')){
                        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/",$type,$values);$allowed=$values[1];
                        $sourceValues=$this->pdo->query('SELECT DISTINCT '.qi($col).' FROM '.$this->table($this->source,$t).' WHERE '.qi($col).' IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
                        if($t==='payment_verifications'&&$col==='status')$sourceValues=array_merge($sourceValues,['pending_verification','paid','refunded','expired']);
                        $all=array_values(array_unique(array_merge($allowed,$sourceValues)));if($all!==$allowed){$type='enum('.implode(',',array_map(fn($s)=>$this->pdo->quote($s),$all)).')';$change=true;}
                    }elseif(preg_match('/^(?:var)?char\((\d+)\)/',$type,$a)&&preg_match('/^(?:var)?char\((\d+)\)/',$meta['Type'],$b)&&(int)$b[1]>(int)$a[1]){$type='varchar('.$b[1].')';$change=true;}
                    $nullable=$dst['Null']==='YES'||$meta['Null']==='YES';if($nullable&&$dst['Null']!=='YES')$change=true;
                    if($change){$sql='ALTER TABLE '.$this->table($this->target,$t).' MODIFY COLUMN '.qi($col).' '.$type.($nullable?' NULL':' NOT NULL');if($dst['Default']!==null)$sql.=' DEFAULT '.$this->pdo->quote($dst['Default']);elseif($nullable)$sql.=' DEFAULT NULL';$this->pdo->exec($sql);}
                }
            }
        }
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS '.$this->table($this->target,'integration_app_archive').' (id bigint unsigned AUTO_INCREMENT PRIMARY KEY, source_table varchar(100) NOT NULL, source_key varchar(500) NOT NULL, original_row json NOT NULL, merge_note varchar(255) NOT NULL, created_at datetime NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    public function merge():void{
        $this->pdo->exec("SET time_zone='+08:00'");
        $ts=tables($this->source);usort($ts,fn($a,$b)=>($a==='users'?-1:($b==='users'?1:strcmp($a,$b))));
        foreach($ts as $t){
            $this->cols[$t]=columns($this->source,$t);$this->keys[$t]=array_values(indexes($this->target,$t));$this->pk[$t]=indexes($this->target,$t)['PRIMARY']??[];
            foreach($this->query('SELECT COLUMN_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$this->source,$t]) as $fk)$this->foreign[$t][$fk['COLUMN_NAME']]=$fk['REFERENCED_TABLE_NAME'];
            $this->app[$t]=$this->query('SELECT * FROM '.$this->table($this->source,$t));$this->web[$t]=$this->query('SELECT * FROM '.$this->table($this->target,$t));
            if(count($this->pk[$t])===1){$p=$this->pk[$t][0];$this->max[$t]=(int)($this->pdo->query('SELECT MAX('.qi($p).') FROM '.$this->table($this->target,$t))->fetchColumn()?:0);foreach($this->app[$t] as $r)if(is_numeric($r[$p]))$this->max[$t]=max($this->max[$t],(int)$r[$p]);foreach($this->web[$t] as $r)$this->used[$t][(string)$r[$p]]=$r;}
        }
        $manual=['migrations'=>['migration'],'categories'=>['name'],'id_types'=>['name'],'payment_methods'=>['name','type']];
        // Account identity must never be inferred from a username or tax identifier.
        $this->keys['users']=array_values(array_filter($this->keys['users'],fn($key)=>in_array($key,[['email'],['google_id']],true)));
        foreach($manual as $t=>$key)if(isset($this->cols[$t])&&!array_diff($key,array_keys($this->cols[$t])))$this->keys[$t][]=$key;
        // Assign all surrogate IDs first, then resolve foreign-key-based unique identities.
        foreach($ts as $t){if(count($this->pk[$t])!==1)continue;$p=$this->pk[$t][0];
            foreach($this->app[$t] as $row){$old=$row[$p];$existing=$this->used[$t][(string)$old]??null;
                if(!$existing){$this->maps[$t][(string)$old]=$old;continue;}
                if(!is_numeric($old)){$this->maps[$t][(string)$old]=$old;continue;}
                $candidate=null;
                foreach($this->keys[$t] as $key){if($key===[$p]||array_filter($key,fn($c)=>$this->ref($t,$c)))continue;
                    foreach($this->web[$t] as $r){$same=true;foreach($key as $c)if(($row[$c]??null)===null||strtolower((string)$row[$c])!==strtolower((string)($r[$c]??'')))$same=false;if($same){$candidate=$r;break 2;}}
                }
                if(!$candidate){$a=$row;$b=array_intersect_key($existing,$row);foreach(array_keys($a) as $c)if(preg_match('/(_at|_on)$/',$c)){unset($a[$c],$b[$c]);}if($a==$b)$candidate=$existing;}
                $this->maps[$t][(string)$old]=$candidate?$candidate[$p]:$this->newId($t,$old);
            }
        }
        for($pass=0;$pass<4;$pass++)foreach($ts as $t)if(count($this->pk[$t])===1){$p=$this->pk[$t][0];foreach($this->app[$t] as $row){$r=$this->transform($t,$row);if($found=$this->matches($t,$r))$this->maps[$t][(string)$row[$p]]=$found[$p];}}
        $this->pdo->beginTransaction();
        try{
            foreach($ts as $t){foreach($this->app[$t] as $row){
                $r=$this->transform($t,$row);$p=$this->pk[$t];$key=json_encode(array_intersect_key($row,array_flip($p)));
                $archive=$row;foreach($archive as &$field)if(is_string($field)&&!mb_check_encoding($field,'UTF-8'))$field=['__pocketfinds_encoding'=>'base64','data'=>base64_encode($field)];unset($field);
                $this->pdo->prepare('INSERT INTO '.$this->table($this->target,'integration_app_archive').' (source_table,source_key,original_row,merge_note,created_at) VALUES (?,?,?,?,?)')->execute([$t,$key,json_encode($archive,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION),'Original app record before ID/timezone reconciliation; binary fields use base64',(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s')]);$this->report['archived_app_rows']++;
                if(in_array($t,['sessions','jobs','job_batches','failed_jobs','cache','cache_locks','mobile_email_challenges','mobile_registration_tokens','account_verification_tokens','personal_access_tokens'],true)){$this->report['quarantined'][$t]=($this->report['quarantined'][$t]??0)+1;continue;}
                $found=$this->matches($t,$r);
                if(!$found && $p){$where=implode(' AND ',array_map(fn($c)=>qi($c).' <=> ?',$p));$found=$this->query('SELECT * FROM '.$this->table($this->target,$t).' WHERE '.$where.' LIMIT 1',array_map(fn($c)=>$r[$c],$p))[0]??null;}
                if($found){
                    $this->report['linked'][$t]=($this->report['linked'][$t]??0)+1;
                    if(array_intersect_key($found,$r)!=$r)$this->report['conflicts'][$t]=($this->report['conflicts'][$t]??0)+1;
                    // Carry app-only columns onto the matching website entity, preserving web fields.
                    $webOriginal=$this->originalColumns[$t];$newCols=array_diff(array_keys($r),$webOriginal);
                    if($newCols && $p){$assign=[];$args=[];foreach($newCols as $c){$assign[]=qi($c).'=?';$args[]=$r[$c];}foreach($p as $c)$args[]=$found[$c];$this->pdo->prepare('UPDATE '.$this->table($this->target,$t).' SET '.implode(',',$assign).' WHERE '.implode(' AND ',array_map(fn($c)=>qi($c).' <=> ?',$p)))->execute($args);}
                    continue;
                }
                $names=array_keys($r);$sql='INSERT INTO '.$this->table($this->target,$t).' ('.implode(',',array_map('qi',$names)).') VALUES ('.implode(',',array_fill(0,count($names),'?')).')';
                try{$this->pdo->prepare($sql)->execute(array_values($r));}catch(Throwable $e){throw new RuntimeException('Merge insert failed in '.$t.' (record values withheld): '.$e->getCode(),0,$e);}
                $this->report['inserted'][$t]=($this->report['inserted'][$t]??0)+1;
            }}
            foreach($this->maps as $t=>$map)foreach($map as $old=>$new)if((string)$old!==(string)$new)$this->report['remapped'][$t][(string)$old]=$new;
            $this->pdo->commit();
        }catch(Throwable $e){$this->pdo->rollBack();throw $e;}
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
