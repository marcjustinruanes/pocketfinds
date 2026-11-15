<?php
declare(strict_types=1);

use App\Models\{User,Order};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Route,URL,View};

/** Run the actual Laravel buyer routes inside the app, without editing the site. */
final class MobileBuyerWeb {
    public function __construct(private Identity $identity) {}

    public static function destination(string $path): string {
        ensure(str_starts_with($path,'/buyer/') && !str_contains($path,'\\') && !str_contains($path,'..')
            && !str_contains($path,"\r") && !str_contains($path,"\n") && !str_starts_with($path,'/buyer/assistant')
            && strlen($path)<1500,'Invalid buyer page.');
        return $path;
    }

    public function handle(string $action,array $data): array {
        $uid=$this->identity->approved('buyer');
        if ($action==='google-email') {
            validateData($data,['id_token'=>'required|string|max:8000']);
            $claims=GoogleIdentity::verify($data['id_token']);
            $email=strtolower((string)$claims['email']);
            ensure((bool)preg_match('/^[^@]+@gmail\.com$/i',$email),'Choose a verified Gmail address.');
            DB::transaction(function()use($uid,$email){
                $user=User::whereKey($uid)->lockForUpdate()->firstOrFail();
                ensure(!User::where('email',$email)->where('id','!=',$uid)->exists(),'This email is already registered.');
                $user->forceFill(['email'=>$email,'email_verified_at'=>now()])->save();
            });
            \Illuminate\Support\Facades\Cache::forget('buyer-email-change:'.$uid);
            return ['verified'=>true];
        }
        ensure($action==='session','Unknown buyer web action.',404);
        ensure($this->identity->token!==null,'Sign in again to open the buyer website.',401);
        $next=self::destination((string)($data['path']??'/buyer/dashboard'));
        $ticket=bin2hex(random_bytes(32));
        // Stable for this native session so multiple in-app web routes agree.
        $nonce=substr(hash_hmac('sha256',$this->identity->token->id.'|'.$this->identity->token->token,(string)config('app.key')),0,48);
        DB::table('personal_access_tokens')->where('tokenable_id',$uid)->where('name','pocketfinds-web-handoff')->where('expires_at','<',now())->delete();
        DB::table('personal_access_tokens')->insert([
            'tokenable_type'=>User::class,'tokenable_id'=>$uid,'name'=>'pocketfinds-web-handoff',
            'token'=>hash('sha256',$ticket),'abilities'=>json_encode(['parent'=>(int)$this->identity->token->id,'nonce'=>$nonce,'next'=>$next]),
            'expires_at'=>now()->addSeconds(60),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return ['ticket'=>$ticket,'bridge_nonce'=>$nonce];
    }

    public static function consume(string $ticket): array {
        ensure((bool)preg_match('/^[a-f0-9]{64}$/D',$ticket),'Invalid sign-in link.',401);
        return DB::transaction(function() use($ticket) {
            $row=DB::table('personal_access_tokens')->where('token',hash('sha256',$ticket))->where('name','pocketfinds-web-handoff')->lockForUpdate()->first();
            ensure($row!==null && strtotime($row->expires_at)>time(),'This sign-in link expired. Reopen the buyer page.',401);
            DB::table('personal_access_tokens')->where('id',$row->id)->delete();
            $details=json_decode($row->abilities,true);
            $parent=DB::table('personal_access_tokens')->where('id',$details['parent'])->where('name','pocketfinds-app')->where('tokenable_id',$row->tokenable_id)->where('tokenable_type',User::class)->where('expires_at','>',now())->first();
            ensure($parent!==null,'Your app session expired.',401);
            $user=User::find($row->tokenable_id); (new Identity($user))->approved('buyer');
            return ['user'=>$user]+$details;
        });
    }

    public static function isWeb(string $path): bool {
        return str_starts_with($path,'buyer/') || str_starts_with($path,'mobile-buyer/')
            || str_starts_with($path,'locations/') || $path==='support/contact'
            || str_starts_with($path,'message-media/') || str_starts_with($path,'report-evidence/')
            || str_starts_with($path,'gcash-purchases') || str_starts_with($path,'account/')
            || str_starts_with($path,'commerce/returns/') || str_starts_with($path,'delivery-proofs/')
            || str_starts_with($path,'private-documents/');
    }

    public static function asset(string $path): bool {
        if (!preg_match('#^((css|js|images|fonts|assets|storage)/|vendor/pdfjs/)#',$path)) return false;
        ensure(!str_contains($path,'..') && !str_contains($path,'\\'),'File not found.',404);
        ensure((bool)preg_match('/\.(css|js|mjs|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|mp4|webm|pdf)$/i',$path),'File not found.',404);
        $root=realpath(public_path()); $file=realpath(public_path($path));
        if (!$file && str_starts_with($path,'storage/')) $file=realpath(storage_path('app/public/'.substr($path,8)));
        // Files under public/storage are public website product/review/profile assets.
        // Sensitive mobile media continue through the existing authorized media API.
        $publicStorage=realpath(storage_path('app/public'));
        ensure($file!==false && is_file($file) && (str_starts_with($file,$root.DIRECTORY_SEPARATOR)
            || ($publicStorage && str_starts_with($file,$publicStorage.DIRECTORY_SEPARATOR))),'File not found.',404);
        ensure(!preg_match('#^storage/(identity-documents|payment-proofs|return-evidence|delivery-proofs|rider-documents|id_files|selfie_files|license_files|vehicle_docs|or_files|cr_files|resume_files|seller_documents|nbi_clearances|business_certificates|business_permits)/#',$path),'File not found.',404);
        $mime=match(strtolower(pathinfo($file,PATHINFO_EXTENSION))) {'css'=>'text/css','js','mjs'=>'text/javascript','svg'=>'image/svg+xml',default=>mime_content_type($file)};
        header('Content-Type: '.$mime); header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600'); header('Accept-Ranges: bytes');
        $size=filesize($file);$start=0;$end=$size-1;
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/D',$_SERVER['HTTP_RANGE'],$range)) {
            if ($range[1]==='') $start=max(0,$size-(int)$range[2]);
            else {$start=(int)$range[1];if($range[2]!=='')$end=min($end,(int)$range[2]);}
            if ($start>$end || $start>=$size) {http_response_code(416);header('Content-Range: bytes */'.$size);return true;}
            http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
        }
        header('Content-Length: '.($end-$start+1));
        if ($_SERVER['REQUEST_METHOD']!=='HEAD') {
            $stream=fopen($file,'rb');fseek($stream,$start);$remaining=$end-$start+1;
            while ($remaining>0 && !feof($stream)) {$chunk=fread($stream,min(65536,$remaining));echo $chunk;$remaining-=strlen($chunk);}
            fclose($stream);
        }
        return true;
    }

    public static function serve(): never {
        // Isolated cookies/files prevent the app from replacing a desktop session.
        $sessions=__DIR__.'/storage/web-sessions'; if (!is_dir($sessions)) mkdir($sessions,0700,true);
        config(['session.driver'=>'file','session.files'=>$sessions,'session.cookie'=>'pocketfinds_mobile_web',
            'session.domain'=>null,'session.secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','session.same_site'=>'lax','session.http_only'=>true]);
        View::getFinder()->prependLocation(__DIR__.'/views');
        app(Illuminate\Contracts\Debug\ExceptionHandler::class)->renderable(fn(ApiError $error)=>response()->json(['message'=>$error->getMessage()],$error->status));
        $request=Request::capture();
        URL::forceRootUrl($request->getSchemeAndHttpHost()); URL::forceScheme($request->getScheme());
        URL::useAssetOrigin($request->getSchemeAndHttpHost());
        config(['app.asset_url'=>null]);
        app('router')->aliasMiddleware('mobile.buyer.web',MobileBuyerWebSession::class);
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string)$route->getName(),'buyer.') || str_starts_with((string)$route->getName(),'gcash.') || str_starts_with((string)$route->getName(),'account.') || in_array($route->getName(),['commerce.return.action','commerce.return.photo','delivery-proofs.show','sensitive-documents.show','support.contact','message.media','report.evidence'],true)) $route->middleware('mobile.buyer.web');
        }
        Route::get('/mobile-buyer/session',function(Request $request) {
            $state=self::consume((string)$request->query('ticket'));
            Auth::login($state['user']); $request->session()->regenerate();
            $request->session()->put('mobile_parent_token',$state['parent']);
            $request->session()->put('mobile_bridge_nonce',$state['nonce']);
            return redirect(self::destination($state['next']))->header('Referrer-Policy','no-referrer');
        })->middleware('web');
        foreach (['settings','checkout','payments'] as $screen) {
            Route::get('/mobile-buyer/'.$screen,fn()=>response('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><p>Opening in PocketFinds…</p></body></html>'))->middleware(['web','mobile.buyer.web']);
        }
        Route::get('/mobile-buyer/bridge.js',fn()=>response(file_get_contents(__DIR__.'/web/bridge.js'),200,['Content-Type'=>'text/javascript','Cache-Control'=>'no-store']));
        // Keep the website checkout and gcash.* controllers unchanged.
        // Their Blade forms, receipt uploads and admin-review rules run in this session.
        $kernel=app(Illuminate\Contracts\Http\Kernel::class); $response=$kernel->handle($request);
        $response->send(); $kernel->terminate($request,$response); exit;
    }
}

final class MobileBuyerWebSession {
    public function handle(Request $request,Closure $next) {
        abort_unless($request->user()?->account_type==='buyer' && $request->user()?->status==='approved',401);
        $parent=DB::table('personal_access_tokens')->where('id',$request->session()->get('mobile_parent_token'))
            ->where('tokenable_type',User::class)->where('tokenable_id',$request->user()->id)->where('name','pocketfinds-app')->where('expires_at','>',now())->first();
        abort_unless($parent && strtotime($parent->last_used_at??$parent->created_at)>=time()-1800,401);
        abort_if($request->is('buyer/assistant*'),404);
        DB::table('personal_access_tokens')->where('id',$parent->id)->update(['last_used_at'=>now()]);
        $response=$next($request);
        if (str_contains($response->headers->get('Content-Type',''),'text/html')) {
            $nonce=json_encode($request->session()->get('mobile_bridge_nonce'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
            $payments=[];
            if ($request->is('buyer/orders') && \Illuminate\Support\Facades\Schema::hasColumn('orders','platform_payment')) {
                foreach (Order::where('buyer_id',$request->user()->id)->where('platform_payment',true)->cursor() as $order) {
                    $payments[]=app(\App\Services\PlatformPayments::class)->detail($request->user(),$order->id);
                }
            }
            $paymentJson=json_encode($payments,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
            $script='<script>window.pocketFindsBridgeNonce='.$nonce.';window.pocketFindsMobilePayments='.$paymentJson.';</script><script src="/mobile-buyer/bridge.js" defer></script>';
            $html=$response->getContent();
            $response->setContent(str_replace('</head>',$script.'</head>',$html));
            $response->headers->remove('Content-Length');
            $response->headers->set('Cache-Control','private, no-store');
            $response->headers->set('Referrer-Policy','same-origin');
        }
        return $response;
    }
}
