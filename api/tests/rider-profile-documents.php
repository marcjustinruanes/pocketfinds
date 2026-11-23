<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require_once dirname(__DIR__).'/Support.php';
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/Actions.php';
require_once dirname(__DIR__).'/Media.php';
use App\Models\{User,LogisticsHub};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
$checks=0;
function checkProfile(bool $condition,string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
    echo 'PASS '.$label.PHP_EOL;
}
DB::beginTransaction();
try {
    $tag=Str::lower(Str::random(12));
    $hub=LogisticsHub::create(['company_name'=>'Rider profile '.$tag,'municipality'=>'Santa Cruz','province'=>'Laguna','is_operational'=>true]);
    $make=function(string $role)use($tag,$hub): User {
        $user=new User;
        $user->forceFill(['email'=>$role.'-'.$tag.'@example.invalid','username'=>$role.'_'.$tag,
            'given_names'=>'Profile','last_name'=>'Fixture','password'=>'Fixture-only-12345!',
            'account_type'=>$role,'status'=>'approved','province'=>'Laguna','municipality'=>'Santa Cruz','barangay'=>'Fixture',
            'business_name'=>$hub->company_name,'logistics_hub_id'=>$hub->id,
            'is_admin'=>false,'is_logistics'=>false,'is_available'=>false]);
        $user->save();
        return $user;
    };
    $rider=$make('rider'); $buyer=$make('buyer');
    $profile=(new MobileActions(new Identity($rider)))->handle('get_my_rider_profile',[]);
    checkProfile((int)$profile['user_id']===(int)$rider->id,'profile belongs to authenticated rider');
    checkProfile($profile['hub_municipality']==='Santa Cruz' && $profile['hub_province']==='Laguna','profile reads assigned hub municipality and province');
    checkProfile($profile['hub_company']===$hub->company_name,'profile reads assigned hub company');
    try {
        (new MobileActions(new Identity($buyer)))->handle('get_my_rider_profile',[]);
        throw new RuntimeException('Buyer accessed rider profile.');
    } catch (ApiError $error) { checkProfile($error->status===403,'buyer cannot use rider profile'); }
    $rider->forceFill(['license_file'=>'fixture-license.pdf','or_file'=>'fixture-or.pdf','cr_file'=>'fixture-cr.pdf'])->save();
    $media=new MobileMedia(new Identity($rider));
    $location=new ReflectionMethod(MobileMedia::class,'location');
    foreach (['legacy-license'=>'fixture-license.pdf','legacy-or'=>'fixture-or.pdf','legacy-cr'=>'fixture-cr.pdf'] as $alias=>$file) {
        checkProfile($location->invoke($media,'rider-documents',$rider->id.'/'.$alias)===['public',$file],$alias.' resolves the recorded document');
    }
    try {
        (new MobileMedia(new Identity($buyer)))->serve('rider-documents',$rider->id.'/legacy-or');
        throw new RuntimeException('Buyer accessed rider documents.');
    } catch (ApiError $error) { checkProfile($error->status===403,'other accounts cannot read rider documents'); }
    echo $checks.' rider profile and document checks passed.'.PHP_EOL;
} finally {
    DB::rollBack();
    echo 'All profile fixtures rolled back.'.PHP_EOL;
}
