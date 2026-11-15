<?php
declare(strict_types=1);

use App\Http\Controllers\RiderController;
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Session\{Store,ArraySessionHandler};
use Illuminate\Support\Facades\{Auth,Cache,Storage};

/** Transport adapter only: the website controller owns delivery, OTP, COD and compensation. */
final class RiderWebsiteDelivery {
    public static function captureKey(int $rider,string $token): string { return 'delivery-proof:'.$rider.':'.$token; }
    public static function capture(int $rider,string $shipment): string {
        $token=bin2hex(random_bytes(32));
        Cache::put(self::captureKey($rider,$token),$shipment,now()->addMinutes(30));
        return $token;
    }
    public static function proof(int $rider,string $path,string $shipment): UploadedFile {
        ensure(preg_match('#^'.preg_quote((string)$rider,'#').'/([a-f0-9]{64})\.jpg$#D',$path,$match)===1,'Capture your own delivery proof.',403);
        ensure(Cache::get(self::captureKey($rider,$match[1]))===$shipment,'Open the camera again to capture fresh delivery proof.');
        $stored='delivery_proofs/pending/'.$path;
        ensure(Storage::disk('sensitive_documents')->exists($stored),'Upload delivery proof first.');
        return new UploadedFile(Storage::disk('sensitive_documents')->path($stored),'delivery.jpg',null,null,true);
    }
    public static function advance(Identity $identity,string $shipment,array $data,?UploadedFile $proof=null): array {
        $request=Request::create('/rider/shipments/'.$shipment.'/advance','POST',$data);
        $request->headers->set('Accept','application/json');
        $request->setUserResolver(fn()=>$identity->user);
        if($proof)$request->files->set('delivery_proof',$proof);
        $session=new Store('mobile-rider',new ArraySessionHandler(30));$session->start();$request->setLaravelSession($session);
        $redirect=app('redirect');$previousSession=app('session.store');$previousUser=Auth::user();$previousRequest=app('request');
        $redirect->setSession($session);Auth::setUser($identity->user);app()->instance('request',$request);
        $writing=MobileDelivery::$writing;MobileDelivery::$writing=true;
        try {
            $response=app(RiderController::class)->advance($request,$shipment);
            $errors=$response->getSession()->get('errors');
            ensure(!$errors || !$errors->any(),$errors?->first()??'Unable to update delivery.',409);
            return ['ok'=>true];
        } finally {
            MobileDelivery::$writing=$writing;$redirect->setSession($previousSession);app()->instance('request',$previousRequest);
            if($previousUser)Auth::setUser($previousUser);else Auth::forgetUser();
        }
    }
}
