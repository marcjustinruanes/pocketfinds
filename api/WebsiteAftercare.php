<?php
declare(strict_types=1);

use App\Models\Order;
use App\Services\BuyerCommerce;
use App\Http\Controllers\CommerceController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};

/** Native transport for the same return records, evidence and decisions as web. */
final class WebsiteAftercare {
    public function __construct(private Identity $identity) {}
    public static function open(string $id): bool {
        return DB::table('commerce_returns')->where('order_id',$id)->whereIn('status',['pending','approved','escalated','received','refunded'])->exists();
    }
    public function details(Order $order): array {
        $row=DB::table('commerce_returns')->where('order_id',$order->id)->first();
        return ['return'=>cleanRow($row),'prepaid'=>(bool)$order->gcash_purchase_id,
            'refund'=>$row?->refund_reference ? ['status'=>$row->status,'reference'=>$row->refund_reference,'amount'=>$row->amount] : null,
            'can_return'=>BuyerCommerce::returnWindowOpen($order) && !$row,
            'can_escalate'=>$row && $row->status==='rejected' && !$row->escalated_at && $row->decided_at>=now()->subDays(3)->toDateTimeString()];
    }
    public function handle(string $action,array $data): array {
        $uid=$this->identity->approved();
        $native=new MobileBuyer($this->identity);
        if($action==='decision') {
            validateData($data,['id'=>'required|uuid']);
            return $native->native($data,fn($unused,$request)=>app(CommerceController::class)->returnAction($request,$data['id']));
        }
        $this->identity->approved('buyer');
        if($action==='returns')return cleanRows(DB::table('commerce_returns')->where('buyer_id',$uid)->latest()->get());
        validateData($data,['order_id'=>'required|uuid']);
        $order=Order::where('buyer_id',$uid)->find($data['order_id']);ensure($order!==null,'Order not found.',404);
        if($action==='escalate') {
            $id=DB::table('commerce_returns')->where('order_id',$order->id)->value('id');ensure($id!==null,'Return not found.',404);
            return $native->native(['action'=>'escalate'],fn($unused,$request)=>app(CommerceController::class)->returnAction($request,$id));
        }
        ensure($action==='return','Unknown return action.',404);
        validateData($data,['request_id'=>'required|uuid','photos'=>'required|array|min:1|max:5','photos.*'=>'required|string']);
        return MobileRequests::run('website-return:'.$uid,$data['request_id'],$data,function()use($data,$order,$native,$uid){
            $paths=MobileAftercare::photos($data['photos'],$uid,'return-evidence');
            $files=array_map(fn($path)=>new UploadedFile(Storage::disk('local')->path('return-evidence/'.$path),basename($path),null,null,true),$paths);
            $native->native(array_diff_key($data,['photos'=>true]),function($unused,$request)use($files,$order){
                $request->files->set('photos',$files);
                return app(CommerceController::class)->requestReturn($request,$order);
            });
            $row=DB::table('commerce_returns')->where('order_id',$order->id)->first();
            DB::afterCommit(function()use($paths){
                foreach($paths as $path){
                    $stored='return-evidence/'.$path;
                    // Imported historical requests may still reference the original upload.
                    if(!DB::table('commerce_returns')->whereJsonContains('photos',$stored)->exists()
                        && !DB::table('mobile_commerce_returns')->whereJsonContains('photos',$path)->exists())Storage::disk('local')->delete($stored);
                }
            });
            return ['submitted'=>true,'id'=>$row->id,'amount'=>$row->amount,'status'=>$row->status];
        });
    }
}
