<?php

// Commission-only runner. Verification refuses real obligations; empty-ledger runs are read-only.
use Illuminate\Support\Facades\{Artisan, DB};

$root=dirname(__DIR__);$lock=null;
try {
    $mode=$argv[1]??'';
    if(!in_array($mode,['--verify-empty','--commission-only'],true))throw new DomainException('An explicit runner mode is required');
    $verifyOnly=$mode==='--verify-empty';
    require $root.'/vendor/autoload.php';
    $app=require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $policy=app(App\Services\CommissionEnrollment::class);
    $enrollment=$policy->state();
    if($verifyOnly&&($enrollment ? $enrollment->enabled : config('commission.enabled')))throw new DomainException('Enrollment must remain disabled');
    if(DB::getDriverName()!=='mysql'||config('database.connections.mysql.host')!=='127.0.0.1'||DB::selectOne('SELECT DATABASE() AS name')->name!=='pocketfinds')throw new DomainException('Unverified database');
    $directory=$root.'/storage/app/private/commission-scheduler';
    if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new DomainException('Private log directory unavailable');
    $lock=fopen($directory.'/verification.lock','c');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new DomainException('Another commission verification is running');
    $before=hash_file('sha256',$root.'/.env');
    $empty=DB::table('commission_obligations')->count()===0;
    $readOnly=$verifyOnly||$empty;
    if($readOnly)DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
    $execute=function()use($readOnly,$verifyOnly,$policy){
        $count=DB::table('commission_obligations')->count();
        if($readOnly&&$count!==0)throw new DomainException('Ledger changed; refuse real processing in this read-only run');
        $started=gmdate('c');
        $exit=Artisan::call('commissions:reconcile');
        if($exit!==0)throw new DomainException('Commission command returned a failure');
        return ['started_at'=>$started,'finished_at'=>gmdate('c'),'command'=>'commissions:reconcile','exit_code'=>$exit,
            'obligations_before'=>$count,'obligations_after'=>DB::table('commission_obligations')->count(),
            'read_only'=>$readOnly,'verification_only'=>$verifyOnly,'enrollment_enabled'=>(bool)($policy->state()?->enabled ?? config('commission.enabled')),'activation_at'=>$policy->state()?->activation_at ?? ($policy->installed()?null:config('commission.activation_at')),'output'=>trim(Artisan::output())];
    };
    // Real servicing keeps the existing command's per-obligation transactions and lock order.
    $report=$readOnly?DB::transaction($execute):$execute();
    if(!hash_equals($before,hash_file('sha256',$root.'/.env')))throw new DomainException('Environment changed during verification');
    $report['environment_unchanged']=true;
    echo json_encode($report,JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $error){
    echo json_encode(['failed'=>true,'at'=>gmdate('c'),'reason'=>$error instanceof DomainException?$error->getMessage():'Verification failed; inspect protected application logs','error_type'=>$error::class]).PHP_EOL;
    exit(1);
} finally {
    if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}
}
