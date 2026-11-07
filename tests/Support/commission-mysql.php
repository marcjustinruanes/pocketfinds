<?php

// Opt-in: php tests/Support/commission-mysql.php --run
// Copies schema only; all records are synthetic. Never changes the configured database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{GcashPurchase, Order, User};
use App\Services\{CommissionLedger, CommissionPayments, CommissionReporting};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

function checkCommission(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function selectCommissionDatabase(string $name): void {
    checkCommission((bool)preg_match('/^pocketfinds_commission_test_[a-f0-9]{12}$/D',$name),'Invalid scratch database');
    config(['database.connections.commission_test'=>array_replace(config('database.connections.mysql'),['database'=>$name,'url'=>null])]);
    DB::purge('commission_test'); DB::setDefaultConnection('commission_test');
    config(['commission.enabled'=>true,'commission.activation_at'=>now()->subMinute()->toIso8601String(),'cache.default'=>'array','filesystems.disks.local.root'=>storage_path('app/private/'.$name)]);
}
function commissionProof(string $label): UploadedFile {
    $path=tempnam(sys_get_temp_dir(),'commission-'); file_put_contents($path,"%PDF-1.4\n".$label);
    return new UploadedFile($path,'proof.pdf','application/pdf',UPLOAD_ERR_OK,true);
}
function synthetic(string $table, array $attributes): array {
    foreach(DB::select('SHOW COLUMNS FROM `'.$table.'`') as $c){
        if(array_key_exists($c->Field,$attributes) || $c->Null==='YES' || $c->Default!==null || str_contains($c->Extra,'auto_increment') || preg_match('/(?:VIRTUAL|STORED) GENERATED/',$c->Extra))continue;
        $type=strtolower($c->Type);
        if(str_starts_with($type,'enum(')){preg_match("/'([^']+)'/",$type,$m);$v=$m[1];}
        elseif(preg_match('/int|decimal|float|double/',$type))$v=0;
        elseif($type==='json')$v='[]';
        elseif(preg_match('/date|timestamp/',$type))$v='2000-01-01 00:00:00';
        else $v='synthetic';
        $attributes[$c->Field]=$v;
    }
    return $attributes;
}
function raceCommission(string $database,string $action,int $actor,string|array $id): array {
    $barrier=storage_path('app/private/commission-barrier-'.bin2hex(random_bytes(6)));$workers=[];
    try{
        for($i=0;$i<2;$i++){
            $p=proc_open([PHP_BINARY,__FILE__,'--worker',$database,$action,(string)$actor,is_array($id)?$id[$i]:$id,$barrier,(string)$i],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),null,['bypass_shell'=>true]);
            checkCommission(is_resource($p),'Could not start race worker');fclose($pipes[0]);$workers[]=[$p,$pipes];
        }
        $deadline=microtime(true)+20;
        while((!file_exists($barrier.'.ready0')||!file_exists($barrier.'.ready1'))&&microtime(true)<$deadline)usleep(10000);
        checkCommission(file_exists($barrier.'.ready0')&&file_exists($barrier.'.ready1'),'Worker start timeout');touch($barrier);
        $results=[];foreach($workers as [$p,$pipes]){$out=trim(stream_get_contents($pipes[1]));$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);checkCommission(proc_close($p)===0,'Worker failed: '.$err.' '.$out);$results[]=$out;}return $results;
    }finally{foreach([$barrier,$barrier.'.ready0',$barrier.'.ready1'] as $path)if(is_file($path))unlink($path);}
}

if(($argv[1]??'')==='--worker'){
    [$database,$action,$actorId,$id,$barrier,$index]=array_slice($argv,2);selectCommissionDatabase($database);$actor=User::findOrFail($actorId);
    touch($barrier.'.ready'.$index);$deadline=microtime(true)+20;while(!file_exists($barrier)&&microtime(true)<$deadline)usleep(10000);
    checkCommission(file_exists($barrier),'Barrier unavailable');
    try{
        if($action==='reconcile'){echo (string)app(CommissionLedger::class)->sweep();exit;}
        elseif($action==='enrollment')app(\App\Services\CommissionEnrollment::class)->change($actor,['action'=>'activate','revision'=>1,'confirmed'=>1,'reason'=>'Synthetic concurrent enrollment activation']);
        elseif($action==='verify')app(CommissionPayments::class)->review($actor,$id,['revision'=>1,'decision'=>'verify','amount'=>'100','reference'=>'MYSQLCOMM001','received'=>1,'reason'=>'Actual synthetic receipt verified']);
        elseif($action==='verify_split'){
            $p=DB::table('commission_payments')->find($id);
            app(CommissionPayments::class)->review($actor,$id,['revision'=>1,'decision'=>'verify','amount'=>\App\Services\LogisticsReconciliation::money($p->amount_cents),'reference'=>$p->reference,'received'=>1,'reason'=>'Actual distinct receipt after partial refund']);
        }
        elseif($action==='reverse')DB::transaction(function()use($id){$o=Order::whereKey($id)->lockForUpdate()->firstOrFail();app(CommissionLedger::class)->reverse($o->id,'return:mysql',[0],'Synthetic partial product repayment');},3);
        elseif($action==='submit'){
            $account=DB::table('commission_accounts')->first();$file=commissionProof('race-'.$index);
            try{app(CommissionPayments::class)->submit($actor,['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'amount'=>'40','reference'=>'MYSQLDUE00'.$index,'sent'=>1,'allocations'=>[$id=>'40']],$file);}finally{unlink($file->getRealPath());}
        }elseif($action==='recovery_verify'){
            $r=DB::table('commission_recoveries')->find($id);
            app(\App\Services\CommissionRecovery::class)->review($actor,$id,['revision'=>1,'decision'=>'verify','amount'=>'100','reference'=>$r->reference,'received'=>1,'ownership_checked'=>1,'reason'=>'Actual receipt and ownership verified in isolated race']);
        }elseif($action==='draft'){
            app(CommissionPayments::class)->prepareRemittance($actor,['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>DB::table('commission_accounts')->value('id'),'allocations'=>[$id=>'100']]);
        }elseif($action==='account_activate'){
            [$accountId,$revision]=explode(':',$id);
            app(\App\Services\CommissionAccounts::class)->manage($actor,['action'=>'activate','account_id'=>$accountId,'revision'=>$revision,'reason'=>'Synthetic competing account activation']);
        }elseif($action==='message'){
            [$paymentId,$submission]=explode(':',$id,2);
            app(\App\Services\CommissionConversations::class)->send($actor,'payment',$paymentId,['submission_key'=>$submission,'message'=>'Concurrent synthetic reply']);
        }else throw new RuntimeException('Invalid worker action');
        echo 'ok';
    }catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){echo 'http:'.$e->getStatusCode();}
    catch(\Illuminate\Validation\ValidationException $e){echo 'validation';}
    exit;
}
if(($argv[1]??'')!=='--run')exit("Use --run for isolated MySQL checks.\n");
checkCommission(DB::getDriverName()==='mysql','MySQL connection required');
$source=DB::connection();$database='pocketfinds_commission_test_'.bin2hex(random_bytes(6));$created=false;
try{
    $source->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $ddl=[];foreach($source->select("SHOW FULL TABLES WHERE Table_type='BASE TABLE'") as $t){$table=array_values((array)$t)[0];if(str_starts_with($table,'commission_') || in_array($table,['cod_refund_reconciliations','cod_residual_settlements']))continue;$ddl[]=array_values((array)$source->selectOne('SHOW CREATE TABLE `'.str_replace('`','``',$table).'`'))[1];}
    selectCommissionDatabase($database);DB::statement('SET FOREIGN_KEY_CHECKS=0');foreach($ddl as $sql)DB::unprepared($sql);DB::statement('SET FOREIGN_KEY_CHECKS=1');
    (require database_path('migrations/2026_11_08_000001_add_platform_commission_ledger.php'))->up();
    (require database_path('migrations/2026_11_08_000002_complete_commission_workflows.php'))->up();
    (require database_path('migrations/2026_11_08_000003_add_commission_account_management.php'))->up();
    (require database_path('migrations/2026_11_08_000004_add_commission_payment_messages.php'))->up();
    $people=[];foreach(['seller','buyer','platform'] as $role){$attrs=synthetic('users',['username'=>'commission-'.$role,'email'=>$role.'@example.invalid','account_type'=>$role==='platform'?'buyer':$role,'status'=>'approved','is_admin'=>$role==='platform','is_logistics'=>false,'given_names'=>'Synthetic','last_name'=>'Commission']);$id=DB::table('users')->insertGetId($attrs);$people[$role]=User::findOrFail($id);}
    $method=DB::table('payment_methods')->insertGetId(synthetic('payment_methods',['name'=>'GCash','type'=>'ewallet','is_active'=>true]));
    $p=GcashPurchase::create(['id'=>(string)Str::uuid(),'buyer_id'=>$people['buyer']->id,'seller_id'=>$people['seller']->id,'checkout_id'=>(string)Str::uuid(),'request_hash'=>hash('sha256','synthetic'),'payment_method_id'=>$method,'snapshot'=>['items'=>[['name'=>'A','qty'=>1,'price'=>600],['name'=>'B','qty'=>1,'price'=>400]],'subtotal'=>'1000','shipping_amount'=>'100','discount_amount'=>'0','commerce'=>['vouchers'=>[]]],'amount_due'=>'1100','payment_state'=>'verified','reservation_state'=>'paid','verified_amount'=>'1100','verified_by'=>$people['seller']->id,'verified_at'=>now(),'payment_reference'=>'MYSQLBUY001']);
    $attrs=synthetic('orders',['id'=>(string)Str::uuid(),'buyer_id'=>$people['buyer']->id,'seller_id'=>$people['seller']->id,'payment_method'=>'GCash','payment_method_id'=>$method,'status'=>'placed','subtotal'=>1000,'total'=>1100,'shipping_amount'=>100,'discount_amount'=>0]);
    $order=(new Order)->forceFill($attrs+['gcash_purchase_id'=>$p->id,'items'=>$p->snapshot['items'],'commerce_snapshot'=>['vouchers'=>[]]]);$order->save();
    DB::table('orders')->where('id',$order->id)->update(['status'=>'completed','buyer_confirmed_at'=>now()]);$order->logStatus('delivered',$people['buyer']->id);$order->refresh();
    $result=raceCommission($database,'reconcile',$people['platform']->id,$order->id);sort($result);
    checkCommission($result===['0','1'],'Concurrent reconciliation counted a transition twice');
    checkCommission(DB::table('commission_events')->where('kind','earned')->count()===1,'Concurrent reconciliation duplicated earning');
    checkCommission(DB::table('notifications')->where('title','Seller commission due')->count()===1,'Concurrent reconciliation duplicated due notification');
    echo "PASS simultaneous automatic reconciliation: one earned transition, event and notification\n";
    $ob=DB::table('commission_obligations')->where('order_id',$order->id)->first();checkCommission($ob && (int)$ob->commission_cents===10000 && $ob->earned_at,'Actual MySQL checkout snapshot/earning failed');
    echo "PASS actual-schema migration, GCash purchase/order snapshot and earning\n";
    $flow=app(CommissionPayments::class);$flow->account($people['platform'],['account_name'=>'Synthetic Platform','account_number'=>'09123456789']);$account=DB::table('commission_accounts')->first();$file=commissionProof('Synthetic MySQL receipt');
    try{$id=$flow->submit($people['seller'],['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'amount'=>'100','reference'=>'MYSQLCOMM001','sent'=>1,'allocations'=>[$ob->id=>'100']],$file);}finally{unlink($file->getRealPath());}
    checkCommission(app(CommissionLedger::class)->collected($ob->id)===0,'Unverified proof counted as collected');
    $result=raceCommission($database,'verify',$people['platform']->id,$id);sort($result);checkCommission($result===['http:409','ok'],'Concurrent receipt verification did not serialize');checkCommission(app(CommissionLedger::class)->collected($ob->id)===10000,'Duplicate/missing receipt allocation');
    echo "PASS simultaneous platform receipt verification: one allocation and one audit\n";
    $result=raceCommission($database,'reverse',$people['platform']->id,$order->id);checkCommission($result===['ok','ok'],'Concurrent refund failed');checkCommission((int)DB::table('commission_obligations')->find($ob->id)->reversed_cents===6000,'Duplicate commission reversal');checkCommission((int)DB::table('commission_credits')->sum('amount_cents')===6000,'Refund credit duplicated');
    echo "PASS simultaneous partial refunds: one reversal and exact Seller credit\n";
    // A second uncollected obligation exercises competing different submission keys.
    $newId=(string)Str::uuid();$copy=(array)DB::table('commission_obligations')->find($ob->id);$copy=array_replace($copy,['id'=>$newId,'source_key'=>'synthetic:'.$newId,'order_id'=>null,'purchase_id'=>null,'commission_cents'=>4000,'reversed_cents'=>0]);DB::table('commission_obligations')->insert($copy);
    $result=raceCommission($database,'submit',$people['seller']->id,$newId);checkCommission($result===['ok','ok'],'Actual Seller transfer claims were lost');
    checkCommission((int)DB::table('commission_allocations')->where('obligation_id',$newId)->sum('requested_cents')===4000,'Competing Seller allocations exceeded outstanding commission');
    echo "PASS simultaneous distinct Seller transfers: both receipts preserved, only one obligation reserved\n";
    checkCommission(app(CommissionReporting::class)->totals(app(CommissionReporting::class)->query($people['seller']))['seller_due']===4000,'MySQL reporting balance differs');
    echo "PASS MySQL report reconciliation and preserved configured database\n";
    $splitId=(string)Str::uuid();$splitCopy=array_replace($copy,['id'=>$splitId,'source_key'=>'synthetic:'.$splitId,'commission_cents'=>10000]);DB::table('commission_obligations')->insert($splitCopy);
    $splitPayments=[];
    foreach(['40','60'] as $index=>$amount){$file=commissionProof('split actual transfer '.$index);try{$splitPayments[]=$flow->submit($people['seller'],['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>DB::table('commission_accounts')->value('id'),'amount'=>$amount,'reference'=>'MYSQLSPLIT00'.$index,'sent'=>1,'allocations'=>[$splitId=>$amount]],$file);}finally{unlink($file->getRealPath());}}
    DB::table('commission_obligations')->where('id',$splitId)->update(['reversed_cents'=>6000]);
    DB::unprepared('CREATE TRIGGER commission_test_pause BEFORE UPDATE ON commission_allocations FOR EACH ROW SET @commission_test_pause = SLEEP(0.25)');
    $result=raceCommission($database,'verify_split',$people['platform']->id,$splitPayments);DB::unprepared('DROP TRIGGER commission_test_pause');
    checkCommission($result===['ok','ok'],'Distinct receipt verification failed');
    checkCommission(app(CommissionLedger::class)->collected($splitId)===4000,'Stale InnoDB snapshot over-collected a partially reversed commission');
    checkCommission((int)DB::table('commission_payments')->whereIn('id',$splitPayments)->sum('unapplied_cents')===6000,'Unapplied receipt credit does not conserve actual transfers');
    echo "PASS distinct simultaneous receipts after refund: current reads cap fees and preserve excess credit\n";
    $attrs=synthetic('users',['username'=>'commission-logistics','email'=>'logistics@example.invalid','account_type'=>'logistics','status'=>'approved','is_admin'=>false,'is_logistics'=>true,'logistics_role'=>'admin','business_name'=>'Commission Test Logistics']);
    $operator=User::findOrFail(DB::table('users')->insertGetId($attrs));$treasury=app(\App\Services\LogisticsTreasury::class);
    $treasury->createAccount($operator,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Synthetic COD','account_number'=>'09222222222']);$account=DB::table('logistics_treasury_accounts')->first();
    $codId=(string)Str::uuid();$copy=array_replace($copy,['id'=>$codId,'source_key'=>'synthetic:'.$codId,'method'=>'cod','commission_cents'=>10000,'withheld_cents'=>10000,'company_name'=>$operator->business_name,'treasury_account_id'=>$account->id]);DB::table('commission_obligations')->insert($copy);
    DB::transaction(fn()=>$treasury->move($operator,$account,'commission',10000,'commission_withheld',(string)Str::uuid()));
    $result=raceCommission($database,'draft',$operator->id,$codId);sort($result);checkCommission($result===['ok','validation'],'COD draft reservation duplicated');
    checkCommission(DB::table('commission_payments')->where('status','draft')->count()===1,'Multiple drafts reserved the same withheld commission');
    checkCommission($treasury->balance($account->id,'commission')===10000 && $treasury->balance($account->id,'comm_transit')===0,'Preparing remittance moved money');
    echo "PASS simultaneous COD remittance preparation: one draft, no money movement\n";
    $recover=app(\App\Services\CommissionRecovery::class);$recoveryIds=[];$receiving=DB::table('commission_accounts')->first();
    foreach([0,1] as $index){$proof=commissionProof('Recovery competing actual claim '.$index);try{$recoveryIds[]=$recover->submit($people['seller'],['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$receiving->id,'reference'=>'MYSQLRECOVERY001','amount'=>'100','reason'=>'Actual transfer with competing proof claim','sent'=>1],$proof);}finally{unlink($proof->getRealPath());}}
    $result=raceCommission($database,'recovery_verify',$people['platform']->id,$recoveryIds);sort($result);checkCommission($result===['ok','validation'],'Competing recovery receipts both verified');
    checkCommission(DB::table('commission_recoveries')->whereIn('id',$recoveryIds)->where('status','verified')->count()===1,'Recovery verified twice');
    checkCommission((int)DB::table('commission_recoveries')->whereIn('id',$recoveryIds)->sum('unapplied_cents')===10000,'Recovery duplicated actual funds');
    echo "PASS competing recovery claims: one verified transfer, one Seller credit\n";
    $settings=app(\App\Services\CommissionAccounts::class);
    $settings->manage($people['platform'],['action'=>'create','revision'=>$settings->settings()->revision,'account_name'=>'Second synthetic account','account_number'=>'09333333333','reason'=>'Synthetic alternate destination']);
    $second=DB::table('commission_account_events')->orderByDesc('id')->value('account_id');
    $revision=$settings->settings()->revision;
    $result=raceCommission($database,'account_activate',$people['platform']->id,[$receiving->id.':'.$revision,$second.':'.$revision]);sort($result);
    checkCommission($result===['http:409','ok'],'Stale competing destination switch was not rejected');
    checkCommission((int)$settings->settings()->revision===$revision+1 && DB::table('commission_account_settings')->count()===1,'Account activation did not serialize');
    checkCommission(DB::table('commission_account_events')->where('action','activate')->count()===1,'Account activation audit duplicated');
    echo "PASS competing GCash account activation: one active pointer, one audit, stale form rejected\n";
    $payment=DB::table('commission_payments')->where('seller_id',$people['seller']->id)->first();$original=(array)$payment;
    $submission=(string)Str::uuid();$notifications=DB::table('notifications')->count();
    $result=raceCommission($database,'message',$people['seller']->id,$payment->id.':'.$submission);sort($result);
    checkCommission($result===['ok','ok'],'Message retry race failed');
    checkCommission(DB::table('commission_payment_messages')->where('submission_key',$submission)->count()===1,'Concurrent message duplicated');
    checkCommission(DB::table('notifications')->count()===$notifications+1,'Concurrent message notification duplicated');
    checkCommission((array)DB::table('commission_payments')->find($payment->id)===$original,'Message changed financial state');
    echo "PASS simultaneous message retries: one message and notification, unchanged financial state\n";
    (require database_path('migrations/2026_11_08_000005_add_commission_enrollment_settings.php'))->up();
    $enrollment=app(\App\Services\CommissionEnrollment::class);
    checkCommission($enrollment->activation()===null,'Default DB policy trusted enabled environment flags');
    $result=raceCommission($database,'enrollment',$people['platform']->id,'synthetic');sort($result);
    checkCommission($result===['http:409','ok'],'Concurrent commission activations did not serialize');
    checkCommission(DB::table('commission_enrollment_events')->count()===1 && (int)$enrollment->state()->revision===2,'Enrollment activation audit duplicated');
    $firstCutoff=$enrollment->state()->activation_at;
    $enrollment->change($people['platform'],['action'=>'deactivate','revision'=>2,'confirmed'=>1,'reason'=>'Synthetic enrollment shutdown']);
    checkCommission($enrollment->activation()===null && $enrollment->state()->activation_at===$firstCutoff,'Deactivation lost activation history');
    checkCommission(app(CommissionLedger::class)->collected($ob->id)===10000,'Deactivation changed verified receipt allocations');
    echo "PASS database enrollment defaults, concurrent activation, audit serialization and preserved payments on deactivation\n";
}finally{
    DB::disconnect('commission_test');
    if($created&&preg_match('/^pocketfinds_commission_test_[a-f0-9]{12}$/D',$database))$source->statement('DROP DATABASE `'.$database.'`');
    $root=storage_path('app/private/'.$database);if(is_dir($root)&&preg_match('/^pocketfinds_commission_test_[a-f0-9]{12}$/D',basename($root)))(new \Illuminate\Filesystem\Filesystem)->deleteDirectory($root);
}
