<?php
// Read-only deployment inventory. Never executes migrations, reconciliation or timestamp conversion.
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Schema};
$ran=DB::table('migrations')->pluck('migration')->all();$pending=[];
foreach(glob(database_path('migrations/*.php')) as $file){
    $name=basename($file,'.php');if(in_array($name,$ran))continue;$source=file_get_contents($file);
    $pending[]=['migration'=>$name,'sha256'=>hash_file('sha256',$file),'contains_data_or_destructive_statements'=>(bool)preg_match('/UPDATE\s|->update\(|dropColumn\(|Schema::drop|ALTER TABLE|->delete\(/i',$source)];
}
$required=['users'=>['is_available','availability_updated_at','nbi_clearance_file'],'delivery_assignments'=>['expires_at','declined_at'],
    'reviews'=>['media'],'orders'=>['gcash_purchase_id','buyer_confirmed_at'],'logistics_issues'=>['investigation'],
    'commission_obligations'=>['activation_at','earned_at','due_at'],'commission_verified_receipts'=>['reference','proof_hash'],
    'commission_accounts'=>['encrypted_details'],'commission_account_settings'=>['active_account_id','revision'],'commission_account_events'=>['account_id','actor_id','revision'],
    'commission_payment_messages'=>['payment_id','recovery_id','sender_id','proof_path'],'commission_payments'=>['review_decision'],'commission_recoveries'=>['review_decision'],
    'commission_enrollment_settings'=>['enabled','activation_at','revision'],'commission_enrollment_events'=>['actor_id','action','previous_activation_at','activation_at','revision','reason']];
$columns=[];foreach($required as $table=>$fields)foreach($fields as $field)$columns[$table.'.'.$field]=Schema::hasColumn($table,$field);
$clock=DB::getDriverName()==='mysql'?DB::selectOne('SELECT @@session.time_zone AS session_timezone, @@global.time_zone AS global_timezone, TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),NOW()) AS session_offset_seconds'):null;
$result=['checked_at'=>gmdate('c'),'read_only'=>true,'app_timezone'=>config('app.timezone'),'mysql_clock'=>$clock,'enrollment'=>config('commission'),'pending'=>$pending,'required_columns'=>$columns,
    'warning'=>'Statement flags are a heuristic over the complete file, including down(); inspect up() and its helpers before deployment. Pending migration history is not proof of missing columns. Do not run blanket migrations or timestamp conversions against mixed-era historical records.'];
$policy=app(\App\Services\CommissionEnrollment::class);
$result['enrollment_source']=$policy->installed()?'database':'legacy_configuration';
$result['enrollment']=$policy->state() ?? config('commission');
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
