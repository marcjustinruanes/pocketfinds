<?php
declare(strict_types=1);

final class MobilePlatformPayments {
    public function __construct(private Identity $identity) {}
    public function handle(string $action, array $data): array {
        $this->identity->approved();
        ensure(class_exists(App\Services\PlatformPayments::class), 'The shared Laravel payment update is not installed.', 503);
        $service = app(App\Services\PlatformPayments::class);
        ensure($service->available(), 'The shared Laravel payment migration is not installed.', 503);
        if (in_array($action,['admin-decision','admin-payout','admin-refund'],true)) {
            ensure((bool)$this->identity->user->is_admin,'Only PocketFinds admin can review payments and payouts.',403);
            if ($action==='admin-payout') {
                validateData($data,['rider_id'=>'required|integer','shipment_id'=>'required|uuid','leg'=>'required|in:pickup,delivery','status'=>'required|in:approved,released,failed,reversed','reference'=>'nullable|string|max:150']);
                return app(App\Services\RiderPayouts::class)->record($this->identity->user,$data);
            }
            validateData($data,['order_id'=>'required|uuid']);
            if ($action==='admin-refund') {
                validateData($data,['reference'=>'required|string|min:5|max:150']);
                return $service->refund($this->identity->user,$data['order_id'],$data['reference']);
            }
            validateData($data,['approve'=>'required|boolean','reason'=>'nullable|string|max:500','can_resubmit'=>'nullable|boolean']);
            return $service->decide($this->identity->user,$data['order_id'],$data['approve'],$data['reason']??null,$data['can_resubmit']??false);
        }
        $this->identity->approved('buyer');
        if ($action === 'statuses') {
            validateData($data,['order_ids'=>'required|array|max:1000','order_ids.*'=>'required|uuid']);
            $statuses=Illuminate\Support\Facades\DB::table('payment_verifications as p')->join('orders as o','o.id','=','p.order_id')
                ->where('o.buyer_id',$this->identity->id())->where('o.platform_payment',true)->whereIn('o.id',$data['order_ids'])->pluck('p.status','o.id')->all();
            $web=Illuminate\Support\Facades\DB::table('gcash_purchases as p')->join('orders as o','o.gcash_purchase_id','=','p.id')->where('o.buyer_id',$this->identity->id())->whereIn('o.id',$data['order_ids'])->pluck('p.payment_state','o.id');
            foreach($web as $oid=>$state)$statuses[$oid]=match($state){'order_placed','verified'=>'paid','proof_submitted'=>'pending_verification','awaiting_payment'=>'pending',default=>$state};
            return $statuses;
        }
        if ($action === 'config') return $service->configuration();
        validateData($data, ['order_id'=>'required|uuid']);
        if ($action === 'detail') return $service->detail($this->identity->user, $data['order_id']);
        ensure($action === 'submit', 'Unknown payment action.', 404);
        validateData($data, ['submission_id'=>'required|uuid', 'reference'=>'required|string|max:20', 'path'=>'required|string|max:255', 'amount'=>'required|numeric|min:0.01']);
        return $service->submit($this->identity->user, $data['order_id'], $data['submission_id'], $data['reference'], $data['path'], (string)$data['amount']);
    }
}
