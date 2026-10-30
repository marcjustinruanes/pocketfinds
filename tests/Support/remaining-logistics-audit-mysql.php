<?php
// Testing only: schema-only random database; no source rows are read or changed.
use Illuminate\Support\Facades\DB;
use App\Models\User;
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!in_array($argv[1]??'', ['--run','--founder','--measure','--rts-receipt','--issue-update'],true)) exit("Use --run\n");
function selectAudit(string $name):void {
 if(!preg_match('/^pocketfinds_audit_test_[a-f0-9]{12}$/D',$name)) throw new RuntimeException('Invalid test database');
 config(['database.connections.audit_test'=>array_replace(config('database.connections.mysql'),['database'=>$name,'url'=>null]),'cache.default'=>'array','mail.default'=>'array','session.driver'=>'array']);
 DB::purge('audit_test');DB::setDefaultConnection('audit_test');
 foreach(['local','public','sensitive_documents'] as $disk) config(['filesystems.disks.'.$disk.'.root'=>storage_path('app/private/'.$name.'/'.$disk)]);
}
if(($argv[1]??'')==='--founder') {
 selectAudit($argv[2]);$index=(int)$argv[3];$barrier=$argv[4];
 $app->instance(App\Services\HubHiring::class,new class($barrier,$index) extends App\Services\HubHiring {
  public function __construct(private string $barrier,private int $index){}
  public function register(array $data,?string $role):User {
   touch($this->barrier.'.ready'.$this->index);$end=microtime(true)+20;
   while(!file_exists($this->barrier) && microtime(true)<$end)usleep(10000);
   if(!file_exists($this->barrier))throw new RuntimeException('Validation barrier timeout');
   if($this->index===1){while(!file_exists($this->barrier.'.committed0') && microtime(true)<$end)usleep(10000); if(!file_exists($this->barrier.'.committed0'))throw new RuntimeException('First commit timeout');}
   return parent::register($data,$role);
  }
 });
 Illuminate\Support\Facades\Mail::fake();Illuminate\Support\Facades\Http::preventStrayRequests();
 $d=['account_type'=>'logistics','auth_method'=>'manual','username'=>'founder_audit_'.$index,'given_names'=>$index?'Second':'First','last_name'=>'Founder','sex'=>'male','birthday'=>'1990-01-01','age'=>36,'email'=>'auditfounder'.$index.'@gmail.com','contact_no'=>'0917000010'.$index,'region'=>'Test Region','province'=>'Laguna','municipality'=>'Test Town '.$index,'barangay'=>'Test Area','password'=>'TestPassword!123','password_confirmation'=>'TestPassword!123','business_name'=>'Audit Shared Company','id_type_id'=>1,'tin'=>'12345678900'.$index,'position'=>'Owner','sec_registration_no'=>'CS12345678'.$index,'postal_code'=>'4000','hub_areas'=>json_encode([['region'=>'Test Region','province'=>'Laguna','municipality'=>'Test Town '.$index]])];
 $root=storage_path('app/private/'.$argv[2]);$path=$root.'/upload'.$index.'.png';file_put_contents($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
 $file=new Illuminate\Http\UploadedFile($path,'document.png','image/png',UPLOAD_ERR_OK,true);
 $r=new Illuminate\Http\Request($d);$r->files->add(['id_file'=>$file,'selfie_file'=>$file,'business_permit_file'=>$file]);
 try{
  DB::beginTransaction();
  $response=app(App\Http\Controllers\RegisterController::class)->store($r);
  if($response->getStatusCode()>=400)DB::rollBack();else DB::commit();
  if($index===0)touch($barrier.'.committed0');
  echo $response->getContent();
 }catch(Throwable $e){if(DB::transactionLevel())DB::rollBack();echo json_encode(['exception'=>get_class($e),'message'=>$e->getMessage()]);exit(1);}exit;
}
if(($argv[1]??'')==='--rts-receipt') {
 selectAudit($argv[2]);$seller=User::where('username','audit-rts-seller')->firstOrFail();
 try{app(App\Services\LogisticsRts::class)->act($seller,$argv[3],['action'=>'seller_received','revision'=>2,'key'=>$argv[3],'reason'=>'Actual full sellable receipt','confirmed'=>1]);echo 'ok';}catch(Throwable $e){echo get_class($e).': '.$e->getMessage();exit(1);}exit;
}
if(($argv[1]??'')==='--issue-update'){
 selectAudit($argv[2]);Illuminate\Support\Facades\Auth::setUser(User::where('username','audit-admin')->firstOrFail());
 try{app(App\Http\Controllers\LogisticsManagementController::class)->updateIssue(new Illuminate\Http\Request(['status'=>'resolved','revision'=>0,'outcome'=>'escalated','resolution'=>'Investigation referred for review']),$argv[3]);echo 'ok';}catch(Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){echo 'http:'.$e->getStatusCode();}exit;
}
if(($argv[1]??'')==='--measure') {
 selectAudit($argv[2]);$actor=User::where('username','audit-admin')->firstOrFail();$queries=0;DB::listen(function()use(&$queries){$queries++;});
 $start=hrtime(true);$base=memory_get_usage(true);
 $r=app(App\Services\LogisticsReconciliation::class)->snapshot($actor,now()->subDay()->toDateString(),now()->addDay()->toDateString());
 echo json_encode(['n'=>(int)$argv[3],'profile'=>$argv[4]??'pending','milliseconds'=>(hrtime(true)-$start)/1e6,'base_bytes'=>$base,'peak_bytes'=>memory_get_peak_usage(true),'queries'=>$queries,'metrics'=>$r['metrics'],'conservation'=>$r['conservation'],'issue_count'=>count($r['issues']),'issue_codes'=>array_values(array_unique(array_column($r['issues'],'code')))]);exit;
}
if(config('database.default')!=='mysql')throw new RuntimeException('MySQL required');
$source=DB::connection('mysql');$database='pocketfinds_audit_test_'.bin2hex(random_bytes(6));
$tables = ['products','users', 'logistics_hubs', 'orders', 'shipments', 'logistics_finance_settings', 'logistics_financial_events',
    'logistics_financial_transactions', 'logistics_cash_handovers', 'logistics_cash_allocations', 'logistics_cash_movements',
    'logistics_shipping_accounts', 'logistics_shipping_rates', 'logistics_shipping_obligations', 'logistics_payment_references', 'logistics_shipping_payments',
    'logistics_treasury_accounts','logistics_payout_destinations','logistics_seller_payouts','logistics_treasury_movements','logistics_finance_evidence',
    'order_status_history','complaints','commerce_returns','delivery_assignments','logistics_compensations','logistics_rider_destinations','logistics_rider_payouts','company_vehicles','shipment_hub_legs','hub_transfer_batches','hub_transfer_members','hub_transfer_events','logistics_issues','logistics_issue_events','notifications','hiring_application_history','hiring_identity_locks','id_types','vehicle_types'];

$tables=array_values(array_unique([...$tables,...array_keys(App\Services\GcashSchema::TABLES),'payment_methods','vouchers','messages']));
$root=storage_path('app/private/'.$database);$results=['environment'=>['php'=>PHP_VERSION,'mysql'=>$source->selectOne('SELECT VERSION() AS version')->version,'worker_memory_limit'=>'512M','samples_per_volume'=>1]];
function seedRow(string $table,array $values): int {
 foreach(DB::select('SHOW COLUMNS FROM `'.$table.'`') as $c)if($c->Null==='NO' && $c->Default===null && !str_contains($c->Extra,'auto_increment') && !array_key_exists($c->Field,$values))$values[$c->Field]=match(true){str_starts_with($c->Type,'enum(')=>str_getcsv(substr($c->Type,5,-1),',',"'",'\\')[0],str_contains($c->Type,'int')=>1,str_contains($c->Type,'date')=>'2000-01-01',default=>'audit-fixture'};
 return DB::table($table)->insertGetId($values);
}
try {
 $source->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');selectAudit($database);mkdir($root,0700,true);
 DB::statement('SET FOREIGN_KEY_CHECKS=0');
 foreach($tables as $table){$ddl=(array)$source->selectOne('SHOW CREATE TABLE `'.$table.'`');DB::unprepared($ddl['Create Table']);}
 (require database_path('migrations/2026_10_29_000006_enforce_logistics_company_owner.php'))->up();
 (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
 (require database_path('migrations/2026_10_29_000009_add_issue_investigation_context.php'))->up();
 for($i=0;$i<256;$i++)DB::table('hiring_identity_locks')->insert(['id'=>$i]);DB::table('id_types')->insert(['id'=>1,'name'=>'Government ID']);
 $barrier=$root.'/founder-barrier';$workers=[];
 for($i=0;$i<2;$i++){$p=proc_open([PHP_BINARY,__FILE__,'--founder',$database,(string)$i,$barrier],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),null,['bypass_shell'=>true]);fclose($pipes[0]);$workers[]=[$p,$pipes];}
 $end=microtime(true)+20;while((!is_file($barrier.'.ready0')||!is_file($barrier.'.ready1'))&&microtime(true)<$end)usleep(10000);touch($barrier);
 foreach($workers as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$results['founder_workers'][]=['exit'=>proc_close($p),'out'=>$out,'err'=>$err];}
 $results['founder_count']=DB::table('users')->where('business_name','Audit Shared Company')->where('logistics_role','admin')->count();
 $successes=0;foreach($results['founder_workers'] as $worker){if($worker['exit']!==0)throw new RuntimeException('Founder worker failed.');if(json_decode($worker['out'],true)['success']??false)$successes++;}
 if($successes!==1 || $results['founder_count']!==1)throw new RuntimeException('Concurrent company ownership not protected.');
 $results['founder_indexes']=array_map(fn($v)=>(array)$v,DB::select('SHOW INDEX FROM users'));
 Illuminate\Support\Facades\Mail::fake();
 foreach(User::where('business_name','Audit Shared Company')->get() as $founder) app(App\Http\Controllers\AdminController::class)->approveUser($founder);
 $results['approved_founder_count']=User::where('business_name','Audit Shared Company')->where('status','approved')->count();
 echo 'Founder count: '.$results['founder_count']."\n";
 if(($argv[2]??'')==='race-only')goto auditDone;
 $hub=DB::table('logistics_hubs')->insertGetId(['company_name'=>'Audit Performance','municipality'=>'Test Town','province'=>'Laguna','is_operational'=>1]);
 $actor=seedRow('users',['username'=>'audit-admin','email'=>'perf@gmail.com','contact_no'=>'09179999999','given_names'=>'Performance','last_name'=>'Admin','account_type'=>'logistics','status'=>'approved','is_admin'=>0,'is_logistics'=>1,'logistics_role'=>'admin','business_name'=>'Audit Performance','logistics_hub_id'=>$hub]);
 $rider=seedRow('users',['username'=>'audit-rider','email'=>'perfrider@gmail.com','contact_no'=>'09179999998','given_names'=>'Performance','last_name'=>'Rider','account_type'=>'rider','status'=>'approved','is_admin'=>0,'is_logistics'=>0,'business_name'=>'Audit Performance','logistics_hub_id'=>$hub]);
 $staff=seedRow('users',['username'=>'audit-staff','email'=>'perfstaff@gmail.com','contact_no'=>'09179999997','given_names'=>'Performance','last_name'=>'Staff','account_type'=>'logistics','status'=>'approved','is_admin'=>0,'is_logistics'=>1,'logistics_role'=>'hub_staff','business_name'=>'Audit Performance','logistics_hub_id'=>$hub]);


 if(in_array($argv[2]??'',['rts-only','issue-only'])){
  app(App\Services\LogisticsShipping::class)->publish(User::findOrFail($actor),['region'=>'Test Region','revision'=>0,'first_kg'=>'100','additional_kg'=>'0']);
  $seller=seedRow('users',['username'=>'audit-rts-seller','municipality'=>'Test Town','province'=>'Laguna','region'=>'Test Region','email'=>'rtsseller@gmail.com','contact_no'=>'09170000999','given_names'=>'RTS','last_name'=>'Seller','account_type'=>'seller','status'=>'approved','is_admin'=>0,'is_logistics'=>0]);
  $buyer=seedRow('users',['username'=>'audit-rts-buyer','email'=>'rtsbuyer@gmail.com','contact_no'=>'09170000998','given_names'=>'RTS','last_name'=>'Buyer','account_type'=>'buyer','status'=>'approved','is_admin'=>0,'is_logistics'=>0]);
  $pid=(string)Illuminate\Support\Str::uuid();seedRow('products',['id'=>$pid,'seller_id'=>$seller,'name'=>'Synthetic RTS','stock'=>4,'price'=>1000,'status'=>'active','weight_grams'=>1000]);
  $o=App\Models\Order::create(['buyer_id'=>$buyer,'seller_id'=>$seller,'status'=>'preparing','payment_method'=>'Cash on Delivery','subtotal'=>1000,'shipping_amount'=>100,'total'=>1100,'commerce_snapshot'=>['vouchers'=>[]],'items'=>[['product_id'=>$pid,'name'=>'Synthetic RTS','qty'=>1,'price'=>1000,'weight_grams'=>1000]],'shipping_address'=>['region'=>'Test Region','province'=>'Laguna','municipality'=>'Test Town']]);
  $s=App\Models\Shipment::create(['order_id'=>$o->id,'logistics_company'=>'Audit Performance','shipping_status'=>'ready_for_pickup','origin_hub'=>'Test Town','origin_province'=>'Laguna','destination_hub'=>'Test Town','destination_province'=>'Laguna']);
  $a=App\Models\DeliveryAssignment::create(['shipment_id'=>$s->id,'courier_id'=>$rider,'leg'=>'delivery','status'=>'assigned_to_rider']);
  DB::table('delivery_assignments')->where('id',$a->id)->update(['status'=>'out_for_delivery']);DB::table('shipments')->where('id',$s->id)->update(['shipping_status'=>'delivery_failed','out_for_delivery_at'=>now()->subHour(),'courier_id'=>$rider]);DB::table('orders')->where('id',$o->id)->update(['status'=>'delivery_failed']);

  if(($argv[2]??'')==='issue-only'){
   $issue=App\Models\LogisticsIssue::create(['shipment_id'=>$s->id,'company_name'=>'Audit Performance','hub_id'=>$hub,'reported_by'=>$actor,'kind'=>'lost','description'=>'Synthetic lost parcel investigation','status'=>'open']);
   $workers=[];for($i=0;$i<2;$i++){$p=proc_open([PHP_BINARY,__FILE__,'--issue-update',$database,(string)$issue->id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),null,['bypass_shell'=>true]);fclose($pipes[0]);$workers[]=[$p,$pipes];}
   $outcomes=[];foreach($workers as [$p,$pipes]){$out=trim(stream_get_contents($pipes[1]));$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException($out.$err);$outcomes[]=$out;}sort($outcomes);
   if($outcomes!==['http:409','ok'] || DB::table('logistics_issue_events')->where('issue_id',$issue->id)->count()!==1)throw new RuntimeException('Issue revision race failed: '.json_encode($outcomes));
   $results['issue_race']='one resolution and one stale conflict';echo "PASS issue resolution race: one event, one stale conflict\n";goto auditDone;
  }
  $f=app(App\Services\LogisticsRts::class);$f->act(User::findOrFail($actor),$s->id,['action'=>'request','revision'=>0,'key'=>(string)Illuminate\Support\Str::uuid(),'reason'=>'Documented return','confirmed'=>1]);$f->act(User::findOrFail($rider),$s->id,['action'=>'to_seller','revision'=>1,'key'=>(string)Illuminate\Support\Str::uuid(),'reason'=>'Actual local return transport','confirmed'=>1]);
  $workers=[];for($i=0;$i<2;$i++){$p=proc_open([PHP_BINARY,__FILE__,'--rts-receipt',$database,$s->id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),null,['bypass_shell'=>true]);fclose($pipes[0]);$workers[]=[$p,$pipes];}
  foreach($workers as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0 || trim($out)!=='ok')throw new RuntimeException('RTS receipt race failed: '.$out.$err);}
  if((int)DB::table('products')->where('id',$pid)->value('stock')!==5 || DB::table('logistics_issue_events')->where('note','like','%seller_received%')->count()!==1)throw new RuntimeException('Duplicate RTS restock/event.');
  $results['rts_receipt_race']='one stock restoration and one Seller receipt event';echo "PASS RTS Seller receipt race: stock once, history once\n";goto auditDone;
 }
 $now=now();$previous=0;
 // Recorded, unremitted synthetic COD obligations. No payment or custody is invented.
 foreach([100,1000,5000,10000] as $n){
  for($i=$previous;$i<$n;$i++)DB::table('logistics_financial_transactions')->insert(['id'=>(string)Illuminate\Support\Str::uuid(),'company_name'=>'Audit Performance','kind'=>'cod_collection','hub_id'=>$hub,'actor_id'=>$rider,'amount_cents'=>10000,'currency'=>'PHP','status'=>'recorded','verification_state'=>'pending','idempotency_key'=>(string)Illuminate\Support\Str::uuid(),'request_hash'=>hash('sha256','audit'.$i),'details'=>'{}','recorded_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
  DB::table('logistics_financial_transactions')->insert(['id'=>(string)Illuminate\Support\Str::uuid(),'company_name'=>'Foreign Performance','kind'=>'cod_collection','hub_id'=>$hub,'actor_id'=>$rider,'amount_cents'=>999999,'currency'=>'PHP','status'=>'recorded','verification_state'=>'pending','idempotency_key'=>(string)Illuminate\Support\Str::uuid(),'request_hash'=>hash('sha256','foreign'.$n),'details'=>'{}','recorded_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
  $p=proc_open([PHP_BINARY,'-d','memory_limit=512M',__FILE__,'--measure',$database,(string)$n],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),null,['bypass_shell'=>true]);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);$r=json_decode($out,true);if($exit!==0||!$r)throw new RuntimeException($err.$out);
  $r['totals_correct']=$r['metrics']['cod_collected']===$n*10000 && $r['metrics']['cod_outstanding']===$n*10000 && $r['metrics']['hub_cash']===0 && $r['conservation']['residual_cents']===0;
  if(!$r['totals_correct'])throw new RuntimeException('Synthetic reconciliation totals mismatch.');
  $results['performance'][]=$r;echo 'Volume '.$n.': '.round($r['milliseconds']).' ms, peak '.$r['peak_bytes'].', totals '.($r['totals_correct']?'correct':'WRONG')."\n";$previous=$n;
 }

 // Dense counted custody profile exercises collection/allocation/movement joins in PHP.
 DB::table('logistics_financial_transactions')->delete();
 $previous=0;
 foreach([100,1000,3000] as $n){
  for($i=$previous;$i<$n;$i++){
   $cid=(string)Illuminate\Support\Str::uuid();$hid=(string)Illuminate\Support\Str::uuid();
   DB::table('logistics_financial_transactions')->insert(['id'=>$cid,'company_name'=>'Audit Performance','kind'=>'cod_collection','hub_id'=>$hub,'actor_id'=>$rider,'amount_cents'=>10000,'currency'=>'PHP','status'=>'recorded','verification_state'=>'verified','idempotency_key'=>(string)Illuminate\Support\Str::uuid(),'request_hash'=>hash('sha256','dense'.$i),'details'=>'{}','recorded_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
   DB::table('logistics_cash_handovers')->insert(['id'=>$hid,'company_name'=>'Audit Performance','kind'=>'remittance','hub_id'=>$hub,'actor_id'=>$rider,'counted_by'=>$staff,'expected_cents'=>10000,'counted_cents'=>10000,'status'=>'verified','idempotency_key'=>(string)Illuminate\Support\Str::uuid(),'request_hash'=>hash('sha256',$hid),'counted_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
   DB::table('logistics_cash_allocations')->insert(['handover_id'=>$hid,'collection_id'=>$cid,'expected_cents'=>10000,'verified_cents'=>10000]);
   DB::table('logistics_cash_movements')->insert(['handover_id'=>$hid,'hub_id'=>$hub,'kind'=>'cash_received','delta_cents'=>10000,'actor_id'=>$staff,'created_at'=>$now]);
  }
  $p=proc_open([PHP_BINARY,'-d','memory_limit=512M',__FILE__,'--measure',$database,(string)$n,'counted'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),null,['bypass_shell'=>true]);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);$r=json_decode($out,true);if($exit!==0||!$r)throw new RuntimeException($err.$out);
  $r['totals_correct']=$r['metrics']['cod_collected']===$n*10000 && $r['metrics']['cod_verified']===$n*10000 && $r['metrics']['hub_cash']===$n*10000 && $r['metrics']['cod_outstanding']===0 && $r['conservation']['residual_cents']===0;
  if(!$r['totals_correct'])throw new RuntimeException('Synthetic reconciliation totals mismatch.');
  $results['performance'][]=$r;echo 'Counted '.$n.': '.round($r['milliseconds']).' ms, peak '.$r['peak_bytes'].', totals '.($r['totals_correct']?'correct':'WRONG')."\n";$previous=$n;
 }

 auditDone:
} catch(Throwable $e){$results['error']=(string)$e;echo $e->getMessage()."\n";
} finally {
 file_put_contents(storage_path('app/private/logistics-ui-review/'.(($argv[2]??'')==='race-only'?'remaining-mysql-race.json':'improvements-mysql-'.(($argv[2]??'')==='rts-only'?'rts':(($argv[2]??'')==='issue-only'?'issue':'performance')).'.json')),json_encode($results,JSON_PRETTY_PRINT));
 DB::disconnect('audit_test');$source->statement('DROP DATABASE IF EXISTS `'.$database.'`');
 if(preg_match('/^pocketfinds_audit_test_[a-f0-9]{12}$/D',basename($root)) && realpath(dirname($root))===realpath(storage_path('app/private'))) (new Illuminate\Filesystem\Filesystem)->deleteDirectory($root);
}
if(isset($results['error']))exit(1);
