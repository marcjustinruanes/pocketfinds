<?php
declare(strict_types=1);
use App\Models\Order;
use App\Services\BuyerCommerce;
use Illuminate\Support\Facades\DB;

/** Native UI transport; checkout execution stays in the website controller. */
final class NativeBuyerCommerce {
    public function __construct(private Identity $identity) {}
    public function handle(string $action,array $data): array {
        $uid=$this->identity->approved('buyer');
        validateData($data,['cart_ids'=>'required|array|min:1|max:100','cart_ids.*'=>'required|uuid|distinct','delivery_address_id'=>'nullable|integer','voucher_codes'=>'nullable|array|max:30']);
        if($action==='quote')return DB::transaction(function()use($data){
            $quote=app(BuyerCommerce::class)->quote($this->identity->user,$data['cart_ids'],$data['delivery_address_id']??null,$data['voucher_codes']??[],empty($data['delivery_address_id']));
            return $quote+['quote_hash'=>hash('sha256',json_encode($quote))];
        });
        ensure($action==='checkout','Unknown checkout action.',404);
        $input=['items'=>$data['cart_ids'],'delivery_address_id'=>$data['delivery_address_id']??null,'payment_method'=>$data['payment_method_id']??null,'checkout_id'=>$data['checkout_id']??null,'quote_hash'=>$data['quote_hash']??null,'vouchers'=>$data['voucher_codes']??[],'buyer_note'=>$data['buyer_note']??''];
        (new MobileBuyer($this->identity))->native($input,fn($unused,$request)=>app(App\Http\Controllers\CommerceController::class)->checkout($request));
        $saved=DB::table('commerce_checkouts')->where('buyer_id',$uid)->where('id',$input['checkout_id'])->firstOrFail();
        $result=json_decode($saved->result,true);
        $orders=Order::where('buyer_id',$uid)->whereIn($result['kind']==='gcash'?'gcash_purchase_id':'id',$result['ids'])->get();
        return $orders->map(fn($o)=>['id'=>$o->id,'order_number'=>$o->order_number,'payment_pending'=>$result['kind']==='gcash','gcash_purchase_id'=>$o->gcash_purchase_id,'platform_payment'=>false])->all();
    }
}
