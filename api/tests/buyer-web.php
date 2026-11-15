<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/Support.php';
require dirname(__DIR__).'/BuyerWeb.php';
use App\Models\{User,Product,CartItem,BuyerAddress,PaymentMethod};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$checks=0;
function verifyWeb(bool $ok,string $message): void { global $checks; if(!$ok)throw new RuntimeException($message); $checks++;echo "PASS $message\n"; }
function rejectWeb(Closure $work,string $message): void { try{$work();}catch(ApiError|Illuminate\Validation\ValidationException $e){verifyWeb(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message); }
DB::beginTransaction();
try {
    $user=new User; $user->forceFill(['email'=>'web-app-'.Str::random(14).'@example.invalid','username'=>'web_app_'.Str::random(12),'given_names'=>'Mobile','last_name'=>'Fixture','account_type'=>'buyer','status'=>'approved','password'=>'Testing-password-123!','province'=>'Laguna','municipality'=>'Famy','barangay'=>'Test','email_verified_at'=>now()]);$user->save();
    $raw=(new Identity($user))->session()['access_token']; $identity=Identity::fromBearer($raw);
    $api=new MobileBuyerWeb($identity);
    rejectWeb(fn()=>(new MobileBuyerWeb(new Identity(null)))->handle('session',[]),'guest cannot issue a web session');
    rejectWeb(fn()=>$api->handle('session',['path'=>'https://example.com/buyer/orders']),'handoff rejects external destinations');
    rejectWeb(fn()=>$api->handle('session',['path'=>'/buyer/../admin/dashboard']),'handoff rejects path traversal');
    rejectWeb(fn()=>$api->handle('session',['path'=>'/buyer/assistant']),'assistant is unavailable in embedded buyer pages');
    $handoff=$api->handle('session',['path'=>'/buyer/account']);
    verifyWeb(strlen($handoff['ticket'])===64 && !isset($handoff['access_token']),'web handoff does not expose the app bearer');
    $state=MobileBuyerWeb::consume($handoff['ticket']);
    verifyWeb($state['user']->id===$user->id && $state['nonce']===$handoff['bridge_nonce'] && $state['next']==='/buyer/account','one-use handoff binds buyer, parent session, nonce and destination');
    rejectWeb(fn()=>MobileBuyerWeb::consume($handoff['ticket']),'handoff cannot be replayed');
    $expired=$api->handle('session',[]); DB::table('personal_access_tokens')->where('token',hash('sha256',$expired['ticket']))->update(['expires_at'=>now()->subSecond()]);
    rejectWeb(fn()=>MobileBuyerWeb::consume($expired['ticket']),'expired handoff is rejected');
    $revoked=$api->handle('session',[]); DB::table('personal_access_tokens')->where('id',$identity->token->id)->delete();
    rejectWeb(fn()=>MobileBuyerWeb::consume($revoked['ticket']),'revoking native session revokes its unused web handoff');
    $user->account_type='rider';
    rejectWeb(fn()=>(new MobileBuyerWeb(new Identity($user)))->handle('session',[]),'rider cannot issue a buyer web session');
    echo "$checks buyer web checks passed.\n";
} finally { DB::rollBack(); echo "Buyer web fixtures rolled back.\n"; }
