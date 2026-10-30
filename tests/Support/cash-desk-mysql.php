<?php

// Opt-in integration check: php tests/Support/cash-desk-mysql.php --run
// Creates and drops only its own randomly named empty MySQL test database.
use App\Models\User;
use App\Services\LogisticsCashDesk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! in_array($argv[1] ?? '', ['--run', '--worker', '--staff-only', '--attendance-only'], true)) exit("Use --run to run the isolated MySQL concurrency checks.\n");
if (config('database.default') !== 'mysql') throw new RuntimeException('A configured MySQL connection is required.');

function selectDatabase(string $database): void {
    if (! preg_match('/^pocketfinds_cash_test_[a-f0-9]{12}$/D', $database)) throw new RuntimeException('Invalid isolated test database name.');
    config(['database.connections.cash_test' => array_replace(config('database.connections.mysql'), ['database' => $database, 'url' => null])]);
    DB::purge('cash_test');
    DB::setDefaultConnection('cash_test');
    config(['cache.default'=>'array','mail.default'=>'array']);
    config(['filesystems.disks.local.root' => storage_path('app/private/'.$database)]);
    config(['filesystems.disks.sensitive_documents.root'=>storage_path('app/private/'.$database.'/sensitive-documents')]);
}

if (($argv[1] ?? '') === '--worker') {
    selectDatabase($argv[2]);
    [$action, $actorId, $id, $barrier, $index] = array_slice($argv, 3, 5);
    touch($barrier.'.ready'.$index);
    $deadline = microtime(true) + 15;
    while (! file_exists($barrier) && microtime(true) < $deadline) usleep(10000);
    if (! file_exists($barrier)) exit(2);
    $desk = app(LogisticsCashDesk::class); $actor = User::findOrFail($actorId);
    try {
        if(str_starts_with($action,'attendance_')) {
            $flow=app(\App\Services\StaffAttendance::class);
            if($action==='attendance_in' || $action==='attendance_out') $flow->clock($actor,substr($action,11));
            elseif($action==='attendance_request'){
                $now=\Carbon\CarbonImmutable::now('Asia/Manila');
                $flow->requestCorrection($actor,['attendance_id'=>$id,'business_date'=>$now->toDateString(),'requested_in'=>$now->subHour()->format('Y-m-d\TH:i'),'requested_out'=>$now->format('Y-m-d\TH:i'),'reason'=>'Synthetic correction concurrency review']);
            }elseif($action==='attendance_approve')$flow->decide($actor,$id,['decision'=>'approve','reason'=>'Synthetic correction evidence verified']);
            else throw new RuntimeException('Unknown attendance race.');
        } elseif($action==='hub_salary_configure') {
            app(\App\Services\HubStaffSalary::class)->configure($actor,['hub_id'=>$id,'monthly'=>'300.01','revision'=>0]);
        } elseif(str_starts_with($action,'staff_salary_')) {
            $f=app(\App\Services\StaffSalary::class);
            if(in_array($action,['staff_salary_bulk_generate','staff_salary_bulk_prepare'],true)) {
                $now=\Carbon\CarbonImmutable::now('Asia/Manila');$w=app(\App\Services\StaffPayrollWorkspace::class);
                $preview=$w->preview($actor,['ids'=>array_map('intval',explode(',',$id)),'mode'=>$action==='staff_salary_bulk_generate'?'generate':'prepare','account_id'=>DB::table('logistics_treasury_accounts')->value('id'),'period_year'=>$now->year,'period_month'=>$now->month,'period_half'=>$now->day<=15?1:2]);
                $w->execute($actor,$preview['token']);
            } elseif($action==='staff_salary_generate') {
                $now=\Carbon\CarbonImmutable::now('Asia/Manila');
                $f->generatePeriod($actor,['staff_id'=>$id,'period_year'=>$now->year,'period_month'=>$now->month,'period_half'=>$now->day<=15?1:2]);
            } elseif($action==='staff_salary_prepare') $f->prepare($actor,['period_id'=>$id,'account_id'=>DB::table('logistics_treasury_accounts')->value('id'),'destination_id'=>DB::table('logistics_staff_destinations')->value('id')]);
            elseif($action==='staff_salary_ack') $f->acknowledge($actor,$id);
            elseif($action==='staff_salary_pay') {
                $path=tempnam(storage_path('app/private/'.$argv[2]),'receipt');file_put_contents($path,"%PDF-1.4\nStaff salary race ".$id);
                try{$f->pay($actor,$id,['revision'=>2,'amount'=>'40','reference'=>'STAFFPAY-'.$id,'sent'=>'1','notes'=>'Actual staff GCash transfer confirmed','payment_date'=>\Carbon\CarbonImmutable::now('Asia/Manila')->toDateString()],new \Illuminate\Http\UploadedFile($path,'evidence.pdf','application/pdf',UPLOAD_ERR_OK,true));}finally{unlink($path);}
            } else throw new RuntimeException('Unknown staff salary race action.');
        } elseif(str_starts_with($action,'hiring_')) {
            \Illuminate\Support\Facades\Auth::setUser($actor);
            if($action==='hiring_create' || $action==='hiring_complete') {
                \Illuminate\Support\Facades\Http::preventStrayRequests();
                \Illuminate\Support\Facades\Http::fake(['https://psgc.gitlab.io/api/regions/'=>\Illuminate\Support\Facades\Http::response([['code'=>'040000000','name'=>'Test Region']]),'https://psgc.gitlab.io/api/provinces/'=>\Illuminate\Support\Facades\Http::response([['code'=>'043400000','name'=>'Laguna','regionCode'=>'040000000']]),'https://psgc.gitlab.io/api/provinces/043400000/cities-municipalities/'=>\Illuminate\Support\Facades\Http::response([['code'=>'043426000','name'=>'Test Origin']]),'https://psgc.gitlab.io/api/cities-municipalities/043426000/barangays/'=>\Illuminate\Support\Facades\Http::response([['code'=>'043426001','name'=>'Test Area']])]);
                $path=tempnam(storage_path('app/private/'.$argv[2]),'receipt');file_put_contents($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
                $file=new \Illuminate\Http\UploadedFile($path,'document.png','image/png',UPLOAD_ERR_OK,true);
                $target=$action==='hiring_complete'?User::findOrFail($id):null;
                $d=['role'=>'rider','username'=>$target?->username??'hiring_worker_'.$index,'email'=>$target?->email??'hiringworker'.$index.'@gmail.com','given_names'=>'Applicant','last_name'=>'Race','sex'=>'male','birthday'=>'1990-01-01','contact_no'=>$target?->contact_no??('0917333000'.$index),'barangay'=>'Test Area','region'=>'Test Region','logistics_hub_id'=>$actor->logistics_hub_id,'password'=>'VerifiedPassword!123','password_confirmation'=>'VerifiedPassword!123','vehicle_type'=>'two_wheels','vehicle_ownership'=>'own','license_number'=>$target?->license_number??'N01-23-123456','license_expiry'=>'2030-01-01','vehicle_brand'=>'Test','vehicle_model'=>'Test','plate_number'=>$target?->plate_number??'SHARED-HIRING-PLATE'];
                $request=new \Illuminate\Http\Request($d);$request->files->add(['license_file'=>$file,'selfie_file'=>$file,'or_file'=>$file,'cr_file'=>$file]);
                try {if($target){$request->request->remove('role');app(\App\Http\Controllers\LogisticsManagementController::class)->updateAccount($request,$target->id);}else app(\App\Http\Controllers\LogisticsManagementController::class)->storeAccount($request);}finally{unlink($path);}
            } elseif($action==='hiring_approve') app(\App\Http\Controllers\LogisticsController::class)->approveRider($id);
            elseif($action==='hiring_staff') app(\App\Http\Controllers\LogisticsController::class)->confirmStaffApproval(new \Illuminate\Http\Request(['interview_completed'=>'1']),$id);
            elseif($action==='hiring_suspend') app(\App\Http\Controllers\LogisticsController::class)->suspendRider(new \Illuminate\Http\Request(['reason_preset'=>'other','reason_details'=>'Actual personnel review']),$id);
            elseif($action==='hiring_activate') app(\App\Http\Controllers\LogisticsController::class)->activateRider(new \Illuminate\Http\Request(['reason_preset'=>'other','reason_details'=>'Review resolved access restored']),$id);
            else throw new RuntimeException('Unknown hiring race.');
        } elseif(str_starts_with($action,'op_')) {
            \Illuminate\Support\Facades\Auth::setUser($actor);
            $a=\App\Models\DeliveryAssignment::findOrFail($id);
            $flow=app(\App\Services\AssignmentReassignment::class);
            if(in_array($action,['op_request','op_retry'])) $flow->request($actor,$id,['key'=>$action==='op_retry'?$a->id:(string)Str::uuid(),'revision'=>0,'old_rider_id'=>$a->courier_id,'replacement_rider_id'=>DB::table('users')->where('username','op-next')->value('id'),'reason'=>'Unavailable rider before physical pickup']);
            elseif($action==='op_confirm') $flow->decide($actor,$id,['key'=>$a->reassignment['key']??$a->id,'revision'=>1,'action'=>'confirm','confirmed'=>'1']);
            elseif($action==='op_pickup') app(\App\Http\Controllers\RiderController::class)->confirmPickupReceipt($a->shipment_id);
            elseif($action==='op_seller_pickup') app(\App\Http\Controllers\SellerController::class)->confirmPickup(\App\Models\Order::findOrFail(\App\Models\Shipment::findOrFail($a->shipment_id)->order_id));
            elseif($action==='op_suspend') DB::transaction(fn()=>app(\App\Http\Controllers\LogisticsController::class)->suspendRider(new \Illuminate\Http\Request(['reason_preset'=>'other','reason_details'=>'Supervised operational suspension']),$a->courier_id),3);
            elseif($action==='op_release') app(\App\Http\Controllers\RiderController::class)->advance(new \Illuminate\Http\Request(['from'=>'assigned_to_rider']),$a->shipment_id);
            elseif($action==='op_cash' || $action==='op_cash_partial') {
                $collection=DB::table('logistics_financial_transactions')->where('collection_shipment_id',$a->shipment_id)->first();
                $desk->submitRecovery($actor,['key'=>(string)Str::uuid(),'rider_id'=>$a->courier_id,'allocations'=>[$collection->id=>$action==='op_cash'?'200':'100'],'reason'=>'Supervised suspended rider cash recovery','reference'=>'OP-CASH-RECOVERY']);
            } elseif($action==='op_count') {
                $collection=DB::table('logistics_financial_transactions')->where('collection_shipment_id',$a->shipment_id)->first();
                $handover=DB::table('logistics_cash_allocations')->where('collection_id',$collection->id)->value('handover_id');
                $desk->count($actor,$handover,['counted'=>'100','allocations'=>[$collection->id=>'100'],'notes'=>'Actual supervised cash count']);
            } elseif($action==='op_recover') {
                $s=\App\Models\Shipment::findOrFail($a->shipment_id);
                app(\App\Services\LogisticsRecovery::class)->receive($actor,['key'=>(string)Str::uuid(),'rider_id'=>$a->courier_id,'leg'=>'delivery','tracking_numbers'=>$s->tracking_number,'condition'=>'Sealed parcel received','reason'=>'Suspended rider physical recovery','reference'=>'OP-CUSTODY','observed_at'=>now('Asia/Manila')->format('Y-m-d H:i:s'),'confirmed'=>'1']);
            } else throw new RuntimeException('Unknown operational race action.');
        } elseif($action==='snapshot_report') {
            $seen=false;
            DB::listen(function($q)use(&$seen,$barrier){
                if(!$seen && str_contains($q->sql,'from `logistics_financial_transactions`')){
                    $seen=true;touch($barrier.'.snapshot-ready');$limit=microtime(true)+12;
                    while(!file_exists($barrier.'.paid') && microtime(true)<$limit)usleep(10000);
                    if(!file_exists($barrier.'.paid'))throw new RuntimeException('Payment could not commit while report snapshot was open.');
                }
            });
            $r=app(\App\Services\LogisticsReconciliation::class)->snapshot($actor,now()->subDay()->toDateString(),now()->addDay()->toDateString());
            $p=collect($r['rows']['rider_payouts'])->firstWhere('id',$id);
            if(!$seen || !$p || $p['status']!=='verified' || $r['conservation']['residual_cents']!==0 || in_array('payout_mirror',array_column($r['issues'],'code')))throw new RuntimeException('Report mixed pre/post-payment records.');
        } elseif($action==='snapshot_pay') {
            $limit=microtime(true)+12;while(!file_exists($barrier.'.snapshot-ready') && microtime(true)<$limit)usleep(10000);
            if(!file_exists($barrier.'.snapshot-ready'))throw new RuntimeException('Report snapshot barrier unavailable.');
            $p=DB::table('logistics_rider_payouts')->where('id',$id)->first();
            $path=tempnam(storage_path('app/private/'.$argv[2]),'receipt');file_put_contents($path,"%PDF-1.4\nConcurrent report payment ".$id);
            try{app(\App\Services\RiderCompensation::class)->pay($actor,$id,['revision'=>$p->revision,'amount'=>\App\Services\LogisticsReconciliation::money((int)$p->amount_cents),'reference'=>'SNAPPAY-'.$id,'sent'=>'1','notes'=>'Actual payment recorded while report snapshot reads'],new \Illuminate\Http\UploadedFile($path,'evidence.pdf','application/pdf',UPLOAD_ERR_OK,true));}finally{unlink($path);}
            touch($barrier.'.paid');
        } elseif (str_starts_with($action,'rider_')) {
            $f=app(\App\Services\RiderCompensation::class);
            if($action==='rider_recognize')$f->recognize($id,$actor);
            elseif($action==='rider_prepare')$f->prepare($actor,['compensation_id'=>$id,'account_id'=>DB::table('logistics_treasury_accounts')->value('id'),'destination_id'=>DB::table('logistics_rider_destinations')->value('id')]);
            elseif($action==='rider_verify')$f->action($actor,$id,['revision'=>1,'action'=>'verify','notes'=>'Verified payroll payment instruction']);
            elseif($action==='rider_hold')$f->action($actor,$id,['revision'=>2,'action'=>'hold','notes'=>'Documented concurrent hold']);
            elseif($action==='rider_ack')$f->acknowledge($actor,$id);
            elseif($action==='rider_pay' || $action==='rider_funding') {
                $path=tempnam(storage_path('app/private/'.$argv[2]),'receipt');file_put_contents($path,"%PDF-1.4\n".$action.' '.$id);
                $file=new \Illuminate\Http\UploadedFile($path,'evidence.pdf','application/pdf',UPLOAD_ERR_OK,true);
                try {
                    if($action==='rider_pay')$f->pay($actor,$id,['revision'=>2,'amount'=>'40','reference'=>'RPAY-'.$id,'sent'=>'1','notes'=>'Actual rider transfer confirmed'],$file);
                    else $f->funding($actor,['key'=>$id,'account_id'=>DB::table('logistics_treasury_accounts')->value('id'),'amount'=>'40','reference'=>'RFUND-'.$id,'received'=>'1','notes'=>'Actual additional payroll contribution'],$file);
                } finally {unlink($path);}
            }else throw new RuntimeException('Unknown rider race action.');
        } elseif ($action === 'treasury_receipt' || $action === 'payout_pay') {
            $filePath=tempnam(storage_path('app/private/'.$argv[2]),'receipt');file_put_contents($filePath,"%PDF-1.4\n".$action.' '.$id);
            $file=new \Illuminate\Http\UploadedFile($filePath,'evidence.pdf','application/pdf',UPLOAD_ERR_OK,true);
            try{
                if($action==='treasury_receipt')app(\App\Services\LogisticsTreasury::class)->receipt($actor,$id,['amount'=>'100','reference'=>'CODDEP-'.$id,'received'=>'1','notes'=>'Actual company bank receipt verified'],$file);
                else app(\App\Services\CodSellerPayouts::class)->pay($actor,$id,['revision'=>2,'amount'=>'100','reference'=>'CODPAY-'.$id,'sent'=>'1','notes'=>'Actual seller bank transfer confirmed'],$file);
            }finally{unlink($filePath);}
        } elseif ($action==='payout_prepare') {
            $order=\App\Models\Order::findOrFail($id);
            app(\App\Services\CodSellerPayouts::class)->prepare($actor,['order_id'=>$id,'account_id'=>DB::table('logistics_treasury_accounts')->value('id'),
                'destination_id'=>DB::table('logistics_payout_destinations')->where('seller_id',$order->seller_id)->value('id'),'adjustment'=>'0']);
        } elseif ($action==='payout_verify') {
            app(\App\Services\CodSellerPayouts::class)->action($actor,$id,['revision'=>1,'action'=>'verify','notes'=>'Source funds and eligibility checked']);
        } elseif ($action==='payout_ack') {
            app(\App\Services\CodSellerPayouts::class)->acknowledge($actor,$id);
        } elseif ($action==='payout_dispute') {
            $p=DB::table('logistics_seller_payouts')->where('id',$id)->first();
            DB::transaction(function()use($p,$actor){
                \App\Services\GcashLocks::order($p->order_id);
                $complaint=(new \App\Models\Complaint())->forceFill(['id'=>(string)Str::uuid(),'order_id'=>$p->order_id,'complainant_id'=>$actor->id,
                    'respondent_id'=>$p->seller_id,'complaint_type'=>'order','subject'=>'Concurrent payout dispute','description'=>'Test dispute filed during settlement','status'=>'open','created_at'=>now()]);
                $complaint->save();
                app(\App\Services\LogisticsFinance::class)->event($p->company_name,'test_dispute_created','seller_payout',$p->id,[],$actor->id);
            },3);
        } elseif ($action === 'parcel_receive') {
            app(\App\Services\HubTransferWorkflow::class)->receive($actor,$id);
        } elseif ($action === 'shipping_submit') {
            $filePath = tempnam(storage_path('app/private/'.$argv[2]), 'receipt');
            file_put_contents($filePath, "%PDF-1.4\nMySQL shipping payment");
            try {
                app(\App\Services\LogisticsShipping::class)->submit($actor, $id, ['key' => (string) Str::uuid(), 'reference' => 'MYSQLSHIP001', 'amount' => '100', 'revision' => 1],
                    new \Illuminate\Http\UploadedFile($filePath, 'receipt.pdf', 'application/pdf', UPLOAD_ERR_OK, true));
            } finally { unlink($filePath); }
        } elseif ($action === 'shipping_review') {
            $payment = DB::table('logistics_shipping_payments')->where('obligation_id', $id)->first();
            app(\App\Services\LogisticsShipping::class)->review($actor, $id, ['payment_id' => $payment->id, 'revision' => 2,
                'accept' => '1', 'amount' => '100', 'reference' => $payment->reference, 'notes' => 'Confirmed actual company receipt']);
        } elseif ($action === 'shipping_ref') {
            DB::transaction(fn () => app(\App\Services\LogisticsShipping::class)->reserveReference('MYSQLSHAREDREF', 'buyer_gcash', (string) Str::uuid()), 3);
        } elseif ($action === 'submit') $desk->submit($actor, ['key' => (string) Str::uuid(), 'allocations' => [$id => '100']]);
        elseif ($action === 'count') {
            $collection = DB::table('logistics_cash_allocations')->where('handover_id', $id)->value('collection_id');
            $desk->count($actor, $id, ['counted' => '100', 'allocations' => [$collection => '100'], 'notes' => 'Concurrent cash count']);
        } else $desk->transfer($actor, $id, $action, ['counted' => '100', 'notes' => 'Concurrent transfer confirmation']);
        echo "ok\n";
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        echo 'http:'.$e->getStatusCode()."\n";
    } catch (\Illuminate\Validation\ValidationException $e) {
        echo "http:422\n";
    } catch (\Illuminate\Database\QueryException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) echo "http:409\n"; else throw $e;
    }
    exit;
}

$source = DB::connection('mysql');
$database = 'pocketfinds_cash_test_'.bin2hex(random_bytes(6));
$tables = ['users', 'logistics_hubs', 'orders', 'shipments', 'logistics_finance_settings', 'logistics_financial_events',
    'logistics_financial_transactions', 'logistics_cash_handovers', 'logistics_cash_allocations', 'logistics_cash_movements',
    'logistics_shipping_accounts', 'logistics_shipping_rates', 'logistics_shipping_obligations', 'logistics_payment_references', 'logistics_shipping_payments',
    'logistics_treasury_accounts','logistics_payout_destinations','logistics_seller_payouts','logistics_treasury_movements','logistics_finance_evidence',
    'order_status_history','complaints','commerce_returns','delivery_assignments','logistics_compensations','logistics_rider_destinations','logistics_rider_payouts','company_vehicles','shipment_hub_legs','hub_transfer_batches','hub_transfer_members','hub_transfer_events','logistics_issues','logistics_issue_events','notifications','logistics_activity_logs','hiring_application_history','hiring_identity_locks','id_types','vehicle_types'];
$barriers = [];
function check(bool $ok, string $message): void { if (! $ok) throw new RuntimeException($message); }
function race(string $database, string|array $action, int|array $actor, string|array $id): array {
    global $barriers;
    $path = storage_path('app/private/cash-race-'.bin2hex(random_bytes(6))); $barriers[] = $path;
    $workers = [];
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $database, is_array($action)?$action[$i]:$action, (string) (is_array($actor) ? $actor[$i] : $actor), is_array($id) ? $id[$i] : $id, $path, (string) $i],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), null, ['bypass_shell' => true]);
        check(is_resource($process), 'Cannot launch MySQL worker.'); fclose($pipes[0]); $workers[] = [$process, $pipes];
    }
    $deadline = microtime(true) + 15;
    while ((! file_exists($path.'.ready0') || ! file_exists($path.'.ready1')) && microtime(true) < $deadline) usleep(10000);
    check(file_exists($path.'.ready0') && file_exists($path.'.ready1'), 'Workers did not reach the start barrier.');
    touch($path);
    $result = [];
    foreach ($workers as [$process, $pipes]) {
        $out = trim(stream_get_contents($pipes[1])); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
        check($code === 0, 'MySQL worker failed: '.$err.' '.$out); $result[] = $out;
    }
    return $result;
}

function checkBulkStaffPayroll(string $database, User $admin, User $salaryStaff, \App\Services\LogisticsTreasury $treasury, object $treasuryAccount, int $beforeCod, int $beforeRider): void
{
    $salaryFlow=app(\App\Services\StaffSalary::class);
    $bulkStaff=[];
    for($i=0;$i<2;$i++){
        $attributes=(array)DB::table('users')->where('id',$salaryStaff->id)->first();unset($attributes['id']);
        foreach(DB::select('SHOW COLUMNS FROM users') as $column) if(str_contains($column->Extra,'GENERATED')) unset($attributes[$column->Field]);
        $attributes['username']='bulk-salary-'.$i;$attributes['email']='bulk-salary-'.$i.'@example.test';$attributes['contact_no']='0914555000'.$i;$attributes['status']='approved';
        $bulkStaff[]=DB::table('users')->insertGetId($attributes);
        $salaryFlow->configure($admin,['staff_id'=>end($bulkStaff),'monthly'=>'80.01','revision'=>0]);
        $salaryFlow->destination(User::find(end($bulkStaff)),['channel'=>'gcash','institution'=>'GCash','account_name'=>'Bulk Staff '.$i,'account_number'=>'0914555100'.$i,'confirmed'=>'1']);
    }
    $bulkIds=implode(',',$bulkStaff);
    check(race($database,'staff_salary_bulk_generate',$admin->id,$bulkIds)===['ok','ok'],'Concurrent bulk generation failed.');
    check(DB::table('logistics_staff_salary_periods')->whereIn('staff_id',$bulkStaff)->count()===2,'Bulk generation duplicated obligations.');
    $mysqlNow=\Carbon\CarbonImmutable::now('Asia/Manila');$w=app(\App\Services\StaffPayrollWorkspace::class);
    $bulkInput=['ids'=>$bulkStaff,'mode'=>'prepare','account_id'=>$treasuryAccount->id,'period_year'=>$mysqlNow->year,'period_month'=>$mysqlNow->month,'period_half'=>$mysqlNow->day<=15?1:2];
    $underfunded=$w->preview($admin,$bulkInput);
    try{$w->execute($admin,$underfunded['token']);throw new RuntimeException('Underfunded bulk selection was accepted.');}catch(\Illuminate\Validation\ValidationException $e){}
    check(DB::table('logistics_staff_payouts')->whereIn('staff_id',$bulkStaff)->count()===0,'Bulk underfunding silently prioritized Staff.');
    $proofPath=tempnam(storage_path('app/private/'.$database),'receipt');file_put_contents($proofPath,"%PDF-1.4\nBulk Staff funding");
    try{$salaryFlow->funding($admin,['key'=>(string)Str::uuid(),'account_id'=>$treasuryAccount->id,'amount'=>'100','reference'=>'BULKSTAFFFUND001','received'=>'1','notes'=>'Separate bulk Staff funding verified'],new \Illuminate\Http\UploadedFile($proofPath,'evidence.pdf','application/pdf',UPLOAD_ERR_OK,true));}finally{unlink($proofPath);}
    $bulkRace=race($database,'staff_salary_bulk_prepare',$admin->id,$bulkIds);check($bulkRace===['ok','ok'],'Concurrent bulk preparation failed: '.json_encode($bulkRace));
    check(DB::table('logistics_staff_payouts')->whereIn('staff_id',$bulkStaff)->count()===2,'Bulk preparation duplicated instructions.');
    $expected=(int)DB::table('logistics_staff_payouts')->whereIn('staff_id',$bulkStaff)->sum('amount_cents');
    check($salaryFlow->available($treasuryAccount->id)===10000-$expected,'Bulk reservations differ from individual obligations.');
    check($treasury->balance($treasuryAccount->id,'cod')===$beforeCod && $treasury->balance($treasuryAccount->id,'rider_payroll')===$beforeRider,'Bulk Staff preparation changed other buckets.');
    echo "PASS concurrent bulk Staff generation/preparation, shortfall rejection and isolated reservations\n";
}

try {
    $source->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    selectDatabase($database);
    mkdir(storage_path('app/private/'.$database), 0700, true);
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $definition = (array) $source->selectOne('SHOW CREATE TABLE `'.$table.'`');
        DB::unprepared($definition['Create Table']);
    }
    foreach(['2026_10_29_000006_enforce_logistics_company_owner','2026_10_29_000007_add_issue_rts_custody','2026_10_29_000008_add_operational_audit_context','2026_10_29_000009_add_issue_investigation_context'] as $migration)(require database_path('migrations/'.$migration.'.php'))->up();
    if(!\Illuminate\Support\Facades\Schema::hasTable('logistics_staff_payouts')) (require database_path('migrations/2026_10_30_000001_add_hub_staff_salary.php'))->up();
    if(!\Illuminate\Support\Facades\Schema::hasColumn('logistics_staff_payouts','payment_date')) (require database_path('migrations/2026_10_30_000002_add_staff_salary_payment_date.php'))->up();
    if(!\Illuminate\Support\Facades\Schema::hasIndex('users','staff_payroll_members')) (require database_path('migrations/2026_10_30_000003_index_staff_payroll_workspace.php'))->up();
    // Some production user columns reference lookup tables; populate only the test users.
    $hub = DB::table('logistics_hubs')->insertGetId(['company_name' => 'Cash Test', 'municipality' => 'Test Origin', 'province' => 'Laguna', 'is_operational' => 1]);
    $destination = DB::table('logistics_hubs')->insertGetId(['company_name' => 'Cash Test', 'municipality' => 'Test Destination', 'province' => 'Laguna', 'is_operational' => 1]);
    $users = [];
    foreach (['rider', 'hub_staff', 'admin', 'destination', 'second_staff', 'seller', 'buyer','second_seller'] as $role) {
        $ordinary = in_array($role, ['rider', 'seller', 'buyer','second_seller']);
        $values = ['username' => 'cash-test-'.$role, 'account_type' => $ordinary ? ($role==='second_seller'?'seller':$role) : 'logistics', 'given_names' => 'Cash', 'last_name' => 'Test',
            'region' => 'Test Region', 'province' => 'Laguna', 'municipality' => 'Test Origin', 'barangay' => 'Test Area',
            'contact_no' => '0912345678'.count($users), 'email' => 'cash-'.$role.'@example.test',
            'business_name' => 'Cash Test', 'status' => 'approved', 'is_admin' => 0, 'is_logistics' => $ordinary ? 0 : 1,
            'logistics_role' => $ordinary ? null : ($role === 'second_admin' ? 'admin' : (in_array($role, ['destination', 'second_staff']) ? 'hub_staff' : $role)),
            'logistics_hub_id' => $role === 'destination' ? $destination : $hub];
        // Supply harmless values for mandatory legacy profile fields in the live schema.
        foreach (DB::select('SHOW COLUMNS FROM users') as $column) {
            if ($column->Null === 'NO' && $column->Default === null && ! str_contains($column->Extra, 'auto_increment') && ! array_key_exists($column->Field, $values)) {
                $values[$column->Field] = match (true) {
                    str_starts_with($column->Type, 'enum(') => str_getcsv(substr($column->Type, 5, -1), ',', "'", '\\')[0],
                    str_contains($column->Type, 'int') => 1,
                    str_contains($column->Type, 'date') => '2000-01-01',
                    default => 'test-'.$role,
                };
            }
        }
        $users[$role] = DB::table('users')->insertGetId($values);
    }
    $users['second_admin']=$users['admin']; // Concurrent sessions of the one authorized company operator.
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    for($bucket=0;$bucket<256;$bucket++) DB::table('hiring_identity_locks')->insert(['id'=>$bucket]);
    DB::table('id_types')->insert(['id'=>1,'name'=>"Driver's License"]);
    DB::table('vehicle_types')->insert(['id'=>1,'slug'=>'two_wheels','name'=>'Motorcycle']);
    if(($argv[1]??'')==='--attendance-only'){
        (require database_path('migrations/2026_10_30_000004_add_hub_salary_and_staff_attendance.php'))->up();
        $admin=User::findOrFail($users['admin']);$member=User::findOrFail($users['hub_staff']);
        $result=race($database,'hub_salary_configure',$admin->id,(string)$hub);sort($result);
        check($result===['http:409','ok'],'Hub revision race did not serialize.');
        check(DB::table('logistics_hub_salary_rates')->count()===1,'Duplicate hub rates.');
        echo "PASS competing hub defaults: one revision, inherited prospective Staff rates\n";
        $result=race($database,'attendance_in',$member->id,'unused');sort($result);
        check($result===['http:422','ok'],'Time In race did not serialize.');
        check(DB::table('logistics_staff_attendance')->whereNotNull('active_staff_id')->count()===1,'Duplicate active shifts.');
        $row=DB::table('logistics_staff_attendance')->first();
        $result=race($database,'attendance_out',$member->id,$row->id);sort($result);
        check($result===['http:422','ok'],'Time Out race did not serialize.');
        check(DB::table('logistics_staff_attendance_events')->whereIn('event',['time_in','time_out'])->count()===2,'Clock audit duplicated.');
        echo "PASS competing Time In/Out: one shift and one audit for each actual action\n";
        $result=race($database,'attendance_request',$member->id,$row->id);sort($result);
        check(in_array($result,[['http:422','ok'],['http:409','ok']],true),'Correction requests duplicated: '.json_encode($result));
        $request=DB::table('logistics_staff_attendance_corrections')->first();
        $result=race($database,'attendance_approve',$admin->id,$request->id);sort($result);
        check($result===['http:409','ok'],'Correction approval duplicated.');
        check(DB::table('logistics_staff_attendance_events')->where('event','correction_applied')->count()===1,'Correction audit duplicated.');
        $changed=DB::table('logistics_staff_attendance')->first();
        check($changed->original_in===$row->original_in && $changed->corrected,'Original clock was overwritten.');
        check(DB::table('logistics_staff_salary_periods')->count()===0 && DB::table('logistics_treasury_movements')->count()===0,'Attendance created payroll transactions.');
        echo "PASS competing correction request/approval: original evidence retained and no money changed\n";
        $work=app(\App\Services\StaffPayrollWorkspace::class);$filters=$work->filters([]);
        $rows=$work->query('Cash Test',$filters)->get();check($rows->count()===3,'Enhanced salary SQL did not return company Staff.');
        $summary=app(\App\Services\StaffAttendance::class)->summary('Cash Test',[$member->id],$changed->business_date,$changed->business_date);
        check($summary[$member->id]['workdays']===1,'MySQL period attendance summary failed.');
        echo "PASS enhanced payroll workspace SQL and period attendance aggregation\n";
        return;
    }
    if(($argv[1]??'')==='--staff-only'){
        $admin=User::findOrFail($users['admin']);$salaryStaff=User::findOrFail($users['second_staff']);
        $salaryFlow=app(\App\Services\StaffSalary::class);$treasury=app(\App\Services\LogisticsTreasury::class);
        $treasury->createAccount($admin,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Bulk Test Treasury','account_number'=>'09123456789']);
        $treasuryAccount=DB::table('logistics_treasury_accounts')->first();$beforeCod=0;$beforeRider=0;
    checkBulkStaffPayroll($database,$admin,$salaryStaff,$treasury,$treasuryAccount,$beforeCod,$beforeRider);
        return;
    }
    $collection = (string) Str::uuid();
    DB::table('logistics_financial_transactions')->insert(['id' => $collection, 'company_name' => 'Cash Test', 'kind' => 'cod_collection',
        'hub_id' => $hub, 'actor_id' => $users['rider'], 'amount_cents' => 10000, 'currency' => 'PHP', 'status' => 'recorded',
        'verification_state' => 'pending', 'idempotency_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', $collection),
        'details' => '{}', 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $results = race($database, 'submit', $users['rider'], $collection); sort($results);
    check($results === ['http:409', 'ok'], 'Concurrent duplicate reservation was not blocked: '.json_encode($results));
    check(DB::table('logistics_cash_allocations')->count() === 1, 'Duplicate allocation.');
    echo "PASS concurrent submission: one reservation, one conflict\n";
    $handover = DB::table('logistics_cash_handovers')->first();
    $results = race($database, 'count', [$users['hub_staff'], $users['second_staff']], $handover->id); sort($results);
    check($results === ['http:409', 'ok'], 'Competing staff counts were not protected.');
    $desk = app(LogisticsCashDesk::class);
    check($desk->custody($hub) === 10000 && DB::table('logistics_cash_movements')->count() === 1, 'Count double-credited custody.');
    echo "PASS concurrent count by different staff: one custody credit and verified allocation\n";
    $transfer = $desk->authorizeTransfer(User::findOrFail($users['admin']), ['key' => (string) Str::uuid(), 'hub_id' => $hub,
        'destination_hub_id' => $destination, 'amount' => '100', 'notes' => 'Isolated concurrency check']);
    check(race($database, 'dispatch', $users['hub_staff'], $transfer->id) === ['ok', 'ok'], 'Dispatch retry failed.');
    check($desk->custody($hub) === 0 && $desk->custody($destination) === 0, 'Dispatch custody incorrect.');
    echo "PASS concurrent dispatch: one debit; cash in transit\n";
    check(race($database, 'receive', $users['destination'], $transfer->id) === ['ok', 'ok'], 'Receipt retry failed.');
    check($desk->custody($destination) === 10000 && DB::table('logistics_cash_movements')->count() === 3, 'Receipt double-credited custody.');
    echo "PASS concurrent receipt: one destination credit\n";
    check(DB::table('logistics_financial_events')->where('event', 'remittance_counted')->count() === 1, 'Duplicate count audit.');
    // Two different staff and transfers compete for the same hub's remaining cash.
    $back = $desk->authorizeTransfer(User::findOrFail($users['admin']), ['key' => (string) Str::uuid(), 'hub_id' => $destination,
        'destination_hub_id' => $hub, 'amount' => '100', 'notes' => 'Return test cash to origin']);
    $desk->transfer(User::findOrFail($users['destination']), $back->id, 'dispatch', ['counted' => '100', 'notes' => 'Released']);
    $desk->transfer(User::findOrFail($users['hub_staff']), $back->id, 'receive', ['counted' => '100', 'notes' => 'Received']);
    $competing = [];
    for ($i = 0; $i < 2; $i++) {
        $competing[] = $desk->authorizeTransfer(User::findOrFail($users['admin']), ['key' => (string) Str::uuid(), 'hub_id' => $hub,
            'destination_hub_id' => $destination, 'amount' => '100', 'notes' => 'Competing authorized transfer'])->id;
    }
    $results = race($database, 'dispatch', [$users['hub_staff'], $users['second_staff']], $competing); sort($results);
    check($results === ['http:422', 'ok'] && $desk->custody($hub) === 0, 'Competing transfers overdrew hub custody.');
    echo "PASS competing transfers by different staff: no hub custody overdraft\n";
    $shipping = app(\App\Services\LogisticsShipping::class); $admin = User::findOrFail($users['admin']);
    $shipping->publish($admin, ['region' => 'Test Region', 'revision' => 0, 'first_kg' => '100', 'additional_kg' => '0']);
    $shipping->configure($admin, ['revision' => 0, 'account_name' => 'Cash Test Logistics', 'account_number' => '09123456789']);
    $order = \App\Models\Order::create(['buyer_id' => $users['buyer'], 'seller_id' => $users['seller'], 'status' => 'preparing', 'payment_method' => 'GCash',
        'subtotal' => 100, 'shipping_amount' => 100, 'discount_amount' => 0, 'total' => 200, 'order_number' => 'MYSQL-'.Str::random(12),
        'items' => [['name' => 'Test parcel', 'qty' => 1, 'price' => 100, 'weight_grams' => 1000]], 'commerce_snapshot' => ['vouchers' => []],
        'shipping_address' => ['region' => 'Test Region', 'municipality' => 'Test Destination', 'province' => 'Laguna']]);
    $shipment = \App\Models\Shipment::create(['order_id' => $order->id, 'logistics_company' => 'Cash Test', 'shipping_status' => 'ready_for_pickup',
        'tracking_number' => 'MYSQL-'.Str::random(12), 'origin_hub' => 'Test Origin', 'origin_province' => 'Laguna', 'destination_hub' => 'Test Destination', 'destination_province' => 'Laguna']);
    $order->update(['status' => 'ready_for_pickup']);
    $ob = DB::table('logistics_shipping_obligations')->where('shipment_id', $shipment->id)->first();
    $results = race($database, 'shipping_submit', $users['seller'], $ob->id); sort($results);
    check($results === ['http:409', 'ok'] || $results === ['http:422', 'ok'], 'Competing shipping submissions were not protected.');
    check(DB::table('logistics_shipping_payments')->count() === 1, 'Duplicate shipping payment.');
    echo "PASS concurrent shipping proof submissions: one payment and reservation\n";
    $results = race($database, 'shipping_review', [$users['admin'], $users['second_admin']], $ob->id); sort($results);
    check($results === ['http:409', 'ok'], 'Competing shipping verification was not protected.');
    check(DB::table('logistics_financial_transactions')->where('shipping_obligation_id', $ob->id)->count() === 1, 'Double shipping settlement.');
    $shipping->gate($shipment->fresh());
    echo "PASS competing Logistics Admin verification: one settlement; pickup gate satisfied\n";
    $results = race($database, 'shipping_ref', [$users['admin'], $users['second_admin']], $ob->id); sort($results);
    check($results === ['http:422', 'ok'] || $results === ['http:409', 'ok'], 'Global GCash reference was reused.');
    echo "PASS concurrent GCash reference reservation: one owner\n";
    $treasury=app(\App\Services\LogisticsTreasury::class);$payoutFlow=app(\App\Services\CodSellerPayouts::class);
    $treasury->createAccount($admin,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Cash Test Treasury','account_number'=>'09123456789']);
    $treasuryAccount=DB::table('logistics_treasury_accounts')->first();
    $codOrders=[];
    foreach(['seller','second_seller'] as $sellerRole){
        $treasury->destination(User::findOrFail($users[$sellerRole]),['channel'=>'gcash','institution'=>'GCash','account_name'=>'Cash Test Seller','account_number'=>'09123456789']);
        $codOrder=\App\Models\Order::create(['buyer_id'=>$users['buyer'],'seller_id'=>$users[$sellerRole],'status'=>'preparing','payment_method'=>'Cash on Delivery',
            'subtotal'=>100,'shipping_amount'=>100,'discount_amount'=>0,'total'=>200,'order_number'=>'CODMYSQL-'.Str::random(10),
            'items'=>[['name'=>'COD parcel','qty'=>1,'price'=>100,'weight_grams'=>1000]],'commerce_snapshot'=>['vouchers'=>[]],
            'shipping_address'=>['region'=>'Test Region','municipality'=>'Test Destination','province'=>'Laguna']]);
        $codShipment=\App\Models\Shipment::create(['order_id'=>$codOrder->id,'logistics_company'=>'Cash Test','shipping_status'=>'ready_for_pickup',
            'tracking_number'=>'CODMYSQL-'.Str::random(10),'origin_hub'=>'Test Origin','origin_province'=>'Laguna','destination_hub'=>'Test Destination','destination_province'=>'Laguna']);
        DB::table('orders')->where('id',$codOrder->id)->update(['status'=>'delivered','buyer_confirmed_at'=>now()]);
        DB::table('shipments')->where('id',$codShipment->id)->update(['shipping_status'=>'delivered','delivered_at'=>now()]);
        $codId=(string)Str::uuid();
        DB::table('logistics_financial_transactions')->insert(['id'=>$codId,'company_name'=>'Cash Test','kind'=>'cod_collection','order_id'=>$codOrder->id,'shipment_id'=>$codShipment->id,
            'collection_order_id'=>$codOrder->id,'collection_shipment_id'=>$codShipment->id,'hub_id'=>$destination,'actor_id'=>$users['rider'],'amount_cents'=>20000,
            'currency'=>'PHP','status'=>'recorded','verification_state'=>'pending','idempotency_key'=>(string)Str::uuid(),'request_hash'=>hash('sha256',$codId),'details'=>'{}','recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $remittance=$desk->submit(User::findOrFail($users['rider']),['key'=>(string)Str::uuid(),'allocations'=>[$codId=>'200']]);
        $desk->count(User::findOrFail($users['destination']),$remittance->id,['counted'=>'200','allocations'=>[$codId=>'200'],'notes'=>'Verified COD test collection']);
        $codOrders[]=$codOrder->id;
    }
    $makeTreasuryTransfer=function()use($treasury,$admin,$destination,$treasuryAccount,$users){
        $treasury->transfer($admin,['key'=>(string)Str::uuid(),'hub_id'=>$destination,'account_id'=>$treasuryAccount->id,'amount'=>'100','notes'=>'Authorized verified COD deposit']);
        $transfer=DB::table('logistics_cash_handovers')->where('kind','treasury')->where('status','authorized')->first();
        $treasury->release(User::findOrFail($users['destination']),$transfer->id,'100');return $transfer;
    };
    $treasuryTransfer=$makeTreasuryTransfer();
    $results=race($database,'treasury_receipt',[$users['admin'],$users['second_admin']],$treasuryTransfer->id);sort($results);
    check($results===['http:409','ok'] && $treasury->balance($treasuryAccount->id,'cod')===10000,'Concurrent treasury receipt double-funded custody.');
    echo "PASS competing treasury receipt: one qualified COD credit\n";
    $results=race($database,'payout_prepare',[$users['admin'],$users['second_admin']],$codOrders);sort($results);
    check($results===['http:422','ok'] && DB::table('logistics_seller_payouts')->count()===1,'Competing payouts over-reserved treasury funds.');
    echo "PASS competing order payouts: one reservation, no over-allocation\n";
    $payout=DB::table('logistics_seller_payouts')->first();
    $results=race($database,'payout_verify',[$users['admin'],$users['second_admin']],$payout->id);sort($results);
    check($results===['http:409','ok'],'Payout verification was not protected.');
    $results=race($database,'payout_pay',[$users['admin'],$users['second_admin']],$payout->id);sort($results);
    check($results===['http:409','ok'] && DB::table('logistics_financial_transactions')->where('seller_payout_id',$payout->id)->count()===1
        && $treasury->balance($treasuryAccount->id,'cod')===0,'Concurrent payout double-debited funds.');
    echo "PASS competing payout verification/payment: one debit and one paid record\n";
    check(race($database,'payout_ack',$payout->seller_id,$payout->id)===['ok','ok'],'Acknowledgement retry failed.');
    check(DB::table('logistics_financial_events')->where('subject_id',$payout->id)->where('event','seller_payout_acknowledged')->count()===1,'Duplicate acknowledgement audit.');
    $remaining=array_values(array_diff($codOrders,[$payout->order_id]))[0];
    $treasuryTransfer=$makeTreasuryTransfer();
    // This second confirmed receipt funds the other order without touching hub operations.
    $path=tempnam(storage_path('app/private/'.$database),'receipt');file_put_contents($path,"%PDF-1.4\nSecond treasury deposit");
    try{$treasury->receipt($admin,$treasuryTransfer->id,['amount'=>'100','reference'=>'SECONDDEP01','received'=>'1','notes'=>'Actual bank receipt'],new \Illuminate\Http\UploadedFile($path,'evidence.pdf','application/pdf',UPLOAD_ERR_OK,true));}finally{unlink($path);}
    $remainingOrder=\App\Models\Order::findOrFail($remaining);
    $payoutFlow->prepare($admin,['order_id'=>$remaining,'account_id'=>$treasuryAccount->id,'destination_id'=>DB::table('logistics_payout_destinations')->where('seller_id',$remainingOrder->seller_id)->value('id'),'adjustment'=>'0']);
    $nextPayout=DB::table('logistics_seller_payouts')->where('order_id',$remaining)->first();
    $payoutFlow->action($admin,$nextPayout->id,['revision'=>1,'action'=>'verify','notes'=>'Verified payout instruction']);
    $results=race($database,['payout_pay','payout_dispute'],[$users['admin'],$users['buyer']],$nextPayout->id);
    $paidEvent=DB::table('logistics_financial_events')->where('subject_id',$nextPayout->id)->where('event','seller_payout_paid')->value('id');
    $disputeEvent=DB::table('logistics_financial_events')->where('subject_id',$nextPayout->id)->where('event','test_dispute_created')->value('id');
    check($results[1]==='ok' && (($results[0]==='http:422' && !$paidEvent) || ($results[0]==='ok' && $paidEvent<$disputeEvent)),'Payment ignored an already-committed dispute.');
    echo "PASS payout/dispute race: shared order lock enforces commit order\n";
    // Phase 6: isolated completed assignments with existing immutable snapshots, no invented production earnings.
    $riderFlow=app(\App\Services\RiderCompensation::class);
    $riderUser=User::findOrFail($users['rider']);
    $riderFlow->destination($riderUser,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Cash Test Rider','account_number'=>'09088776655']);
    $riderEarnings=[];
    foreach($codOrders as $orderId) {
        $ship=DB::table('shipments')->where('order_id',$orderId)->first();
        DB::table('shipments')->where('id',$ship->id)->update(['courier_id'=>$riderUser->id,'delivered_at'=>now()]);
        $assignment=(string)Str::uuid();DB::table('delivery_assignments')->insert(['id'=>$assignment,'shipment_id'=>$ship->id,'courier_id'=>$riderUser->id,'leg'=>'delivery','status'=>'delivered','delivered_at'=>now()]);
        $cid=(string)Str::uuid();DB::table('logistics_compensations')->insert(['id'=>$cid,'company_name'=>'Cash Test','source_type'=>'assignment','source_id'=>$assignment,'rider_id'=>$riderUser->id,'hub_id'=>$hub,'leg'=>'delivery','amount_cents'=>4000,'settings_revision'=>1,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        $riderEarnings[]=$cid;
    }
    check(race($database,'rider_recognize',[$users['admin'],$users['second_admin']],$riderEarnings[0])===['ok','ok'],'Recognition retry failed.');
    check(DB::table('logistics_financial_events')->where('subject_id',$riderEarnings[0])->where('event','rider_compensation_earned')->count()===1,'Duplicate earned compensation.');
    $riderFlow->recognize($riderEarnings[1],$admin);
    echo "PASS competing rider earning recognition: one entitlement and audit\n";
    $bid=(string)Str::uuid();DB::table('hub_transfer_batches')->insert(['id'=>$bid,'company_name'=>'Cash Test','origin_hub_id'=>$hub,'destination_hub_id'=>$destination,'rider_id'=>$riderUser->id,'status'=>'completed','dispatched_at'=>now(),'received_at'=>now(),'received_by'=>$users['destination'],'legacy_import'=>0,'created_at'=>now(),'updated_at'=>now()]);
    foreach($codOrders as $oid){$sid=DB::table('shipments')->where('order_id',$oid)->value('id');$lid=(string)Str::uuid();
        DB::table('shipment_hub_legs')->insert(['id'=>$lid,'shipment_id'=>$sid,'sequence'=>1,'leg_type'=>'direct','from_hub'=>'Test Origin','from_province'=>'Laguna','to_hub'=>'Test Destination','to_province'=>'Laguna','status'=>'completed','rider_id'=>$riderUser->id,'rider_confirmed_pickup_at'=>now(),'completed_at'=>now(),'transfer_batch_id'=>$bid]);
        DB::table('hub_transfer_members')->insert(['batch_id'=>$bid,'leg_id'=>$lid,'shipment_id'=>$sid,'received_by'=>$users['destination'],'received_at'=>now()]);
    }
    $batchComp=(string)Str::uuid();DB::table('logistics_compensations')->insert(['id'=>$batchComp,'company_name'=>'Cash Test','source_type'=>'transfer_batch','source_id'=>$bid,'rider_id'=>$riderUser->id,'hub_id'=>$hub,'leg'=>'transfer','amount_cents'=>15000,'settings_revision'=>1,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
    // Live receipt races exercise the new physical-custody boundary in an isolated schema.
    DB::table('hub_transfer_batches')->where('id',$bid)->update(['status'=>'in_transit','received_at'=>null,'received_by'=>null]);
    DB::table('hub_transfer_members')->where('batch_id',$bid)->update(['received_at'=>null,'received_by'=>null]);
    DB::table('shipment_hub_legs')->where('transfer_batch_id',$bid)->update(['status'=>'in_transit','completed_at'=>null]);
    foreach($codOrders as $oid) {
        DB::table('orders')->where('id',$oid)->update(['status'=>'hub_transfer']);
        DB::table('shipments')->where('order_id',$oid)->update(['shipping_status'=>'hub_transfer','hub_transfer_rider_id'=>$riderUser->id]);
    }
    $results=race($database,'parcel_receive',[$users['admin'],$users['destination']],$bid);sort($results);
    check($results===['http:403','ok'],'Admin attested physical batch receipt: '.json_encode($results));
    check(DB::table('hub_transfer_batches')->where('id',$bid)->value('received_by')==$users['destination'],'Receipt actor is not destination staff.');
    check(DB::table('hub_transfer_events')->where('batch_id',$bid)->where('action','received')->count()===1,'Duplicate batch arrival.');
    $results=race($database,'parcel_receive',$users['destination'],$bid);sort($results);
    check($results===['http:422','http:422'],'Completed batch receipt was replayed.');
    echo "PASS physical batch receipt race: Admin denied; destination staff receives once and one trip earns\n";
    check(race($database,'rider_recognize',[$users['admin'],$users['second_admin']],$batchComp)===['ok','ok'],'Batch earning retries failed.');
    check(DB::table('logistics_financial_events')->where('subject_id',$batchComp)->where('event','rider_compensation_earned')->count()===1 && DB::table('logistics_compensations')->where('source_id',$bid)->where('status','earned')->sum('amount_cents')==15000,'Transfer earned per parcel or twice.');
    echo "PASS competing completed transfer recognition: one trip entitlement for two parcels\n";

    $fundKey=(string)Str::uuid();$results=race($database,'rider_funding',[$users['admin'],$users['second_admin']],$fundKey);sort($results);
    check($results===['http:409','ok'],'Duplicate payroll funding accepted: '.json_encode($results));
    check($treasury->balance($treasuryAccount->id,'rider_payroll')===4000,'Payroll credited twice.');
    echo "PASS competing rider funding: one verified contribution\n";
    $results=race($database,'rider_prepare',[$users['admin'],$users['second_admin']],$riderEarnings);sort($results);
    check($results===['http:422','ok'],'Rider reservations overspent payroll: '.json_encode($results));
    check(DB::table('logistics_rider_payouts')->count()===1 && $riderFlow->available($treasuryAccount->id)===0,'Duplicate payroll reservation.');
    $rp=DB::table('logistics_rider_payouts')->first();
    $results=race($database,'rider_verify',[$users['admin'],$users['second_admin']],$rp->id);sort($results);check($results===['http:409','ok'],'Duplicate rider verification.');
    $codBalance=$treasury->balance($treasuryAccount->id,'cod');
    $results=race($database,'rider_pay',[$users['admin'],$users['second_admin']],$rp->id);sort($results);check($results===['http:409','ok'],'Duplicate rider payment: '.json_encode($results));
    check($treasury->balance($treasuryAccount->id,'rider_payroll')===0 && $treasury->balance($treasuryAccount->id,'cod')===$codBalance,'Rider payout consumed COD funds or double-debited payroll.');
    check(DB::table('logistics_financial_transactions')->where('rider_payout_id',$rp->id)->count()===1,'Duplicate rider ledger payment.');
    check(race($database,'rider_ack',$riderUser->id,$rp->id)===['ok','ok'],'Rider acknowledgement retry failed.');
    check(DB::table('logistics_financial_events')->where('subject_id',$rp->id)->where('event','rider_payout_acknowledged')->count()===1,'Duplicate rider acknowledgement.');
    echo "PASS competing rider reservations, verification, payment and acknowledgement: payroll remains separate\n";
    // A committed hold wins against a stale payment instruction; no provider transfer is simulated.
    $fundKey=(string)Str::uuid();$results=race($database,'rider_funding',$users['admin'],$fundKey);sort($results);check($results===['http:409','ok'],'Second payroll contribution duplicated.');
    $remainingEarning=array_values(array_diff($riderEarnings,[$rp->compensation_id]))[0];
    $riderFlow->prepare($admin,['compensation_id'=>$remainingEarning,'account_id'=>$treasuryAccount->id,'destination_id'=>DB::table('logistics_rider_destinations')->value('id')]);
    $next=DB::table('logistics_rider_payouts')->where('compensation_id',$remainingEarning)->first();
    $riderFlow->action($admin,$next->id,['revision'=>1,'action'=>'verify','notes'=>'Verified rider instruction']);
    $results=race($database,['rider_hold','rider_pay'],[$users['admin'],$users['second_admin']],$next->id);
    check(in_array($results,[['ok','http:409'],['http:409','ok']],true),'Concurrent hold and payment both succeeded: '.json_encode($results));
    $state=DB::table('logistics_rider_payouts')->where('id',$next->id)->first();
    check(($state->hold_reason && $state->status==='verified' && $treasury->balance($treasuryAccount->id,'rider_payroll')===4000) || (!$state->hold_reason && $state->status==='paid' && $treasury->balance($treasuryAccount->id,'rider_payroll')===0),'Hold/payment outcome inconsistent.');
    echo "PASS rider hold/payment race: one committed instruction, no silent offset\n";
    $path=tempnam(storage_path('app/private/'.$database),'receipt');file_put_contents($path,"%PDF-1.4\nAdditional verified trip payroll contribution");
    try{$riderFlow->funding($admin,['key'=>(string)Str::uuid(),'account_id'=>$treasuryAccount->id,'amount'=>'150','reference'=>'SNAPFUND001','received'=>'1','notes'=>'Actual additional payroll funding verified'],new \Illuminate\Http\UploadedFile($path,'evidence.pdf','application/pdf',UPLOAD_ERR_OK,true));}finally{unlink($path);}
    $riderFlow->prepare($admin,['compensation_id'=>$batchComp,'account_id'=>$treasuryAccount->id,'destination_id'=>DB::table('logistics_rider_destinations')->value('id')]);
    $tripPayout=DB::table('logistics_rider_payouts')->where('compensation_id',$batchComp)->first();$riderFlow->action($admin,$tripPayout->id,['revision'=>1,'action'=>'verify','notes'=>'Verified trip payout instruction']);
    check(race($database,['snapshot_report','snapshot_pay'],[$users['second_admin'],$users['admin']],$tripPayout->id)===['ok','ok'],'Report/payment snapshot test failed.');
    $final=app(\App\Services\LogisticsReconciliation::class)->snapshot($admin,now()->subDay()->toDateString(),now()->addDay()->toDateString());
    check($final['conservation']['residual_cents']===0 && collect($final['rows']['rider_payouts'])->firstWhere('id',$tripPayout->id)['status']==='paid','Final reconciliation does not reflect committed payout.');
    echo "PASS reconciliation snapshot during committed payment: no write blocking or mixed ledger/balance view\n";
    // New operational fixtures exist only in this random isolated database.
    $opUsers=[];
    foreach(['op-old','op-next'] as $name){
        $row=(array)DB::table('users')->find($users['rider']);unset($row['id'],$row['company_owner_key']);
        $row['username']=$name;$row['email']=$name.'@example.test';$row['contact_no']=$name==='op-old'?'09170000001':'09170000002';
        $row['status']='approved';$row['vehicle_type']='two_wheels';$row['vehicle_ownership']='own';$row['logistics_hub_id']=$hub;
        foreach(['license_number','plate_number'] as $column)if(array_key_exists($column,$row))$row[$column]=$name;
        $opUsers[$name]=DB::table('users')->insertGetId($row);
    }
    $makeOperation=function(bool $delivery=false)use($users,$opUsers){
        $o=\App\Models\Order::create(['buyer_id'=>$users['buyer'],'seller_id'=>$users['seller'],'status'=>'preparing','payment_method'=>'Cash on Delivery','subtotal'=>100,'shipping_amount'=>100,'discount_amount'=>0,'total'=>200,'order_number'=>'OP-'.Str::random(12),
            'items'=>[['name'=>'Operational parcel','qty'=>1,'price'=>100,'weight_grams'=>1000]],'commerce_snapshot'=>['vouchers'=>[]],'shipping_address'=>['region'=>'Test Region','municipality'=>'Test Origin','province'=>'Laguna']]);
        $s=\App\Models\Shipment::create(['order_id'=>$o->id,'logistics_company'=>'Cash Test','shipping_status'=>'ready_for_pickup','tracking_number'=>'OP-'.Str::random(12),'origin_hub'=>'Test Origin','origin_province'=>'Laguna','destination_hub'=>'Test Origin','destination_province'=>'Laguna']);
        $values=['pickup_approved_at'=>now(),'pickup_rider_id'=>$opUsers['op-old']];
        if($delivery)$values+=['shipping_status'=>'assigned_to_rider','courier_id'=>$opUsers['op-old'],'sorted_at'=>now(),'at_sorting_center_at'=>now(),'picked_up_at'=>now(),'seller_confirmed_pickup_at'=>now(),'rider_confirmed_pickup_at'=>now()];
        DB::table('shipments')->where('id',$s->id)->update($values);
        DB::table('orders')->where('id',$o->id)->update(['status'=>$delivery?'assigned_to_rider':'ready_for_pickup']);
        return \App\Models\DeliveryAssignment::create(['shipment_id'=>$s->id,'courier_id'=>$opUsers['op-old'],'leg'=>$delivery?'delivery':'pickup','status'=>$delivery?'assigned_to_rider':'accepted','accepted_at'=>now()]);
    };
    $a=$makeOperation();$results=race($database,'op_request',$users['admin'],$a->id);sort($results);
    check($results===['http:409','ok'],'Competing reassignment proposals: '.json_encode($results));
    $results=race($database,'op_confirm',$users['seller'],$a->id);sort($results);
    check($results===['http:409','ok'] && $a->fresh()->courier_id===$opUsers['op-next'],'Competing possession confirmations: '.json_encode($results));
    check(DB::table('logistics_financial_events')->where('subject_id',$a->id)->where('event','assignment_reassigned_confirmed')->count()===1,'Duplicate confirmed reassignment event.');
    $a=$makeOperation();check(race($database,'op_retry',$users['admin'],$a->id)===['ok','ok'],'Identical proposal retries were not idempotent.');
    check($a->fresh()->revision===1,'Retry changed the assignment revision twice.');
    $a=$makeOperation();$results=race($database,['op_request','op_pickup'],[$users['admin'],$opUsers['op-old']],$a->id);
    check(in_array($results,[['ok','http:409'],['http:422','ok']],true),'Reassignment/pickup race: '.json_encode($results));
    check(!($a->fresh()->reassignment && \App\Models\Shipment::find($a->shipment_id)->rider_confirmed_pickup_at),'Proposal coexists with physical pickup.');
    $a=$makeOperation();DB::table('shipments')->where('id',$a->shipment_id)->update(['rider_confirmed_pickup_at'=>now()]);
    check(race($database,['op_request','op_seller_pickup'],[$users['admin'],$users['seller']],$a->id)===['http:422','ok'],'Recorded handover was overwritten during seller confirmation.');
    $a=$makeOperation(true);$results=race($database,['op_request','op_release'],[$users['admin'],$opUsers['op-old']],$a->id);
    check(in_array($results,[['ok','http:409'],['http:422','ok']],true),'Reassignment/release race: '.json_encode($results));
    $a=$makeOperation();$results=race($database,['op_suspend','op_pickup'],[$users['admin'],$opUsers['op-old']],$a->id);
    check($results[0]==='ok' && in_array($results[1],['ok','http:403'],true),'Suspension/pickup race: '.json_encode($results));
    check(User::find($opUsers['op-old'])->status==='suspended','Suspension not preserved.');
    echo "PASS operational races: competing and duplicate proposals, seller attestation, pickup/release and suspension boundaries\n";
    DB::table('users')->where('id',$opUsers['op-old'])->update(['status'=>'approved']);
    $a=$makeOperation(true);\Illuminate\Support\Facades\Auth::setUser(User::findOrFail($opUsers['op-old']));
    app(\App\Http\Controllers\RiderController::class)->advance(new \Illuminate\Http\Request(['from'=>'assigned_to_rider']),$a->shipment_id);
    DB::table('users')->where('id',$opUsers['op-old'])->update(['status'=>'suspended']);
    $results=race($database,'op_recover',[$users['hub_staff'],$users['second_staff']],$a->id);sort($results);
    check($results===['http:422','ok'],'Competing physical recoveries: '.json_encode($results));
    check(DB::table('logistics_issues')->whereJsonContains('recovery->shipment_ids',$a->shipment_id)->count()===1 && \App\Models\Shipment::find($a->shipment_id)->shipping_status==='out_for_delivery','Recovery fabricated parcel progression.');
    check(app(\App\Services\AssignmentWork::class)->classify($a->fresh())==='ambiguous','Recovery did not hold the actual rider/leg availability.');
    echo "PASS competing Staff parcel recovery: one custody event; no delivery or compensation\n";
    DB::table('users')->where('id',$opUsers['op-old'])->update(['status'=>'approved']);
    $a=$makeOperation(true);\Illuminate\Support\Facades\Auth::setUser(User::findOrFail($opUsers['op-old']));
    app(\App\Http\Controllers\RiderController::class)->advance(new \Illuminate\Http\Request(['from'=>'assigned_to_rider']),$a->shipment_id);
    app(\App\Http\Controllers\RiderController::class)->advance(new \Illuminate\Http\Request(['from'=>'out_for_delivery','cod_amount'=>'200','cod_confirmed'=>'1','collection_key'=>(string)Str::uuid()]),$a->shipment_id);
    DB::table('users')->where('id',$opUsers['op-old'])->update(['status'=>'suspended']);
    $results=race($database,'op_cash',[$users['hub_staff'],$users['second_staff']],$a->id);sort($results);
    check($results===['http:409','ok'],'Recovery cash competing reservations: '.json_encode($results));
    $h=DB::table('logistics_cash_handovers')->where('actor_id',$opUsers['op-old'])->sole();
    check($h->submitted_by!==null && !$h->counted_at,'Recovery submission invented a physical count.');
    $c=DB::table('logistics_financial_transactions')->where('collection_shipment_id',$a->shipment_id)->first();
    $desk->count(User::findOrFail($users['hub_staff']),$h->id,['counted'=>'200','allocations'=>[$c->id=>'200'],'notes'=>'Actual supervised recovery count']);
    $report=app(\App\Services\LogisticsReconciliation::class)->snapshot($admin,now()->subDay()->toDateString(),now()->addDay()->toDateString());
    check($report['conservation']['residual_cents']===0,'Recovery count broke conservation.');
    echo "PASS suspended cash recovery: one reservation, real submitter, separate physical count, coherent funds\n";
    DB::table('users')->where('id',$opUsers['op-old'])->update(['status'=>'approved']);
    $a=$makeOperation(true);\Illuminate\Support\Facades\Auth::setUser(User::findOrFail($opUsers['op-old']));
    app(\App\Http\Controllers\RiderController::class)->advance(new \Illuminate\Http\Request(['from'=>'assigned_to_rider']),$a->shipment_id);
    app(\App\Http\Controllers\RiderController::class)->advance(new \Illuminate\Http\Request(['from'=>'out_for_delivery','cod_amount'=>'200','cod_confirmed'=>'1','collection_key'=>(string)Str::uuid()]),$a->shipment_id);
    DB::table('users')->where('id',$opUsers['op-old'])->update(['status'=>'suspended']);
    $c=DB::table('logistics_financial_transactions')->where('collection_shipment_id',$a->shipment_id)->first();
    $desk->submitRecovery(User::findOrFail($users['hub_staff']),['key'=>(string)Str::uuid(),'rider_id'=>$opUsers['op-old'],'allocations'=>[$c->id=>'100'],'reason'=>'Partial suspended rider recovery','reference'=>'OP-PARTIAL']);
    check(race($database,['op_count','op_cash_partial'],[$users['hub_staff'],$users['second_staff']],$a->id)===['ok','ok'],'Count versus remaining recovery reservation failed.');
    $balance=$desk->balances($c);check($balance['verified']===10000 && $balance['reserved']===10000 && $balance['available']===0,'Concurrent recovery/count overallocated the collection.');
    echo "PASS concurrent physical count and remaining recovery submission: verified and reserved amounts stay distinct\n";
    \Illuminate\Support\Facades\Auth::setUser($admin);
    $results=race($database,'hiring_create',$users['admin'],(string)Str::uuid());sort($results);
    check($results===['http:422','ok'],'Competing managed identity/licence applications: '.json_encode($results));
    $new=User::where('username','like','hiring_worker_%')->sole();
    check($new->status==='pending' && $new->id_file===$new->license_file && app(\App\Services\PersonnelApplication::class)->readable($new->license_file),'Managed creation bypassed readiness or private storage.');
    $new->update(['license_file'=>null]);
    $results=race($database,['hiring_complete','hiring_approve'],$users['admin'],(string)$new->id);
    check($results[0]==='ok' && in_array($results[1],['ok','http:422'],true),'Completion/approval race: '.json_encode($results));
    if($new->fresh()->status==='pending') app(\App\Http\Controllers\LogisticsController::class)->approveRider($new->id);
    check($new->fresh()->status==='approved','Evidence-complete application could not approve.');
    $results=race($database,'hiring_suspend',$users['admin'],(string)$new->id);sort($results);check($results===['http:422','ok'],'Competing suspensions: '.json_encode($results));
    $results=race($database,'hiring_activate',$users['admin'],(string)$new->id);sort($results);check($results===['http:422','ok'],'Competing reactivations: '.json_encode($results));
    check($new->fresh()->status==='approved' && $new->fresh()->status_reason===null && DB::table('hiring_application_history')->where('user_id',$new->id)->where('status','suspended')->count()===1,'Decision history duplicated or reason not cleared.');
    $staffRow=(array)DB::table('users')->find($users['hub_staff']);unset($staffRow['id'],$staffRow['company_owner_key']);
    $staffRow=array_replace($staffRow,['username'=>'hiring_staff_fixture','email'=>'staff_fixture@gmail.com','contact_no'=>'09173339999','license_number'=>'STAFF-HIRING-LICENCE','plate_number'=>'STAFF-HIRING-PLATE','status'=>'interview','id_type_id'=>1,'id_file'=>$new->fresh()->license_file,'selfie_file'=>$new->fresh()->selfie_file,'interview_scheduled_at'=>now(),'interview_location'=>'Actual test hub']);
    $staffId=DB::table('users')->insertGetId($staffRow);
    $results=race($database,'hiring_staff',$users['admin'],(string)$staffId);sort($results);check($results===['http:422','ok'],'Competing Staff interview approvals: '.json_encode($results));
    $staffEvents=DB::table('hiring_application_history')->where('user_id',$staffId)->get();check($staffEvents->count()===1 && json_decode($staffEvents[0]->review_details,true)['interview_completed']===true,'Interview attestation missing or duplicated.');
    echo "PASS managed application identity/private files, completion versus approval, suspension and reactivation races\n";
    echo "PASS competing Staff approval: one recorded interview-completion attestation\n";
    $salaryFlow=app(\App\Services\StaffSalary::class);
    $salaryStaff=User::findOrFail($users['second_staff']);
    $salaryFlow->configure($admin,['staff_id'=>$salaryStaff->id,'monthly'=>'80.00','revision'=>0]);
    check(race($database,'staff_salary_generate',$admin->id,$salaryStaff->id)===['ok','ok'],'Staff period generation retry failed.');
    check(DB::table('logistics_staff_salary_periods')->count()===1,'Duplicate staff salary obligations.');
    $salaryPeriod=DB::table('logistics_staff_salary_periods')->first();
    $salaryFlow->destination($salaryStaff,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Salary Staff','account_number'=>'09123456001','confirmed'=>'1']);
    $proofPath=tempnam(storage_path('app/private/'.$database),'receipt');file_put_contents($proofPath,"%PDF-1.4\nSeparate Staff funding");
    try{$salaryFlow->funding($admin,['key'=>(string)Str::uuid(),'account_id'=>$treasuryAccount->id,'amount'=>'40','reference'=>'STAFFFUND001','received'=>'1','notes'=>'Separate Staff contribution verified'],new \Illuminate\Http\UploadedFile($proofPath,'evidence.pdf','application/pdf',UPLOAD_ERR_OK,true));}finally{unlink($proofPath);}
    $results=race($database,'staff_salary_prepare',$admin->id,$salaryPeriod->id);sort($results);
    check($results===['http:409','ok'],'Duplicate Staff salary reservation: '.json_encode($results));
    $salaryPayout=DB::table('logistics_staff_payouts')->first();
    check($salaryFlow->available($treasuryAccount->id)===0,'Staff reservation missing.');
    $salaryFlow->action($admin,$salaryPayout->id,['revision'=>1,'action'=>'verify','notes'=>'Staff instruction checked']);
    $beforeCod=$treasury->balance($treasuryAccount->id,'cod');$beforeRider=$treasury->balance($treasuryAccount->id,'rider_payroll');
    $results=race($database,'staff_salary_pay',$admin->id,$salaryPayout->id);sort($results);
    check($results===['http:409','ok'],'Duplicate Staff salary payment: '.json_encode($results));
    check($treasury->balance($treasuryAccount->id,'staff_payroll')===0 && $treasury->balance($treasuryAccount->id,'cod')===$beforeCod && $treasury->balance($treasuryAccount->id,'rider_payroll')===$beforeRider,'Staff payment funding isolation failed.');
    check(race($database,'staff_salary_ack',$salaryStaff->id,$salaryPayout->id)===['ok','ok'],'Staff acknowledgement retry failed.');
    check(DB::table('logistics_financial_events')->where('subject_id',$salaryPayout->id)->where('event','staff_payout_acknowledged')->count()===1,'Duplicate Staff acknowledgement event.');
    echo "PASS competing Staff salary generation, reservation, payment and acknowledgement; funding remains isolated\n";
    checkBulkStaffPayroll($database,$admin,$salaryStaff,$treasury,$treasuryAccount,$beforeCod,$beforeRider);
    echo "PASS audit events recorded once; source database unchanged\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL '.get_class($e).': '.$e->getMessage().PHP_EOL);
    $failed = true;
} finally {
    $root = storage_path('app/private/'.$database);
    // Delete only known test receipt files inside this script's isolated storage root.
    if (is_dir($root) && preg_match('/^pocketfinds_cash_test_[a-f0-9]{12}$/D', basename($root))) {
        if(is_dir($root.'/sensitive-documents')) (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($root.'/sensitive-documents');
        foreach (glob($root.'/logistics-shipping-proofs/*') ?: [] as $file) if (is_file($file)) unlink($file);
        if (is_dir($root.'/logistics-shipping-proofs')) rmdir($root.'/logistics-shipping-proofs');
        foreach(glob($root.'/logistics-finance-proofs/*')?:[] as $file)if(is_file($file))unlink($file);
        if(is_dir($root.'/logistics-finance-proofs'))rmdir($root.'/logistics-finance-proofs');
        foreach (glob($root.'/receipt*') ?: [] as $file) if (is_file($file)) unlink($file);
        rmdir($root);
    }
    DB::disconnect('cash_test');
    // Exact generated name was validated by selectDatabase; never remove the configured database.
    $source->statement('DROP DATABASE IF EXISTS `'.$database.'`');
    foreach ($barriers as $path) foreach ([$path, $path.'.ready0', $path.'.ready1', $path.'.snapshot-ready', $path.'.paid'] as $file) if (is_file($file)) unlink($file);
}
if ($failed ?? false) exit(1);
