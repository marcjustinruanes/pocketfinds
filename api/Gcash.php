<?php
declare(strict_types=1);
use App\Models\GcashPurchase;
use App\Services\GcashWorkflow;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MobileGcash {
    public function __construct(private Identity $identity) {}
    public function handle(string $action,array $data): mixed {
        $workflow=app(GcashWorkflow::class);
        if (str_starts_with($action,'staff-')) {
            $this->identity->approved('seller');
            validateData($data,['id'=>'required|uuid','revision'=>'required|integer']);
            if ($action==='staff-instructions') {
                validateData($data,['name'=>'required|string|max:150','number'=>['required','regex:/^09[0-9]{9}$/D']]);
                $p=$workflow->instructions($this->identity->user,$data['id'],$data['revision'],$data['name'],$data['number']);
            } elseif ($action==='staff-decision') {
                validateData($data,['accept'=>'required|boolean','amount'=>'nullable|numeric|min:0','reference'=>'nullable|string|max:100','reason'=>'nullable|string|min:10|max:500']);
                $p=$workflow->decide($this->identity->user,$data['id'],$data['revision'],$data['accept'],isset($data['amount'])?(string)$data['amount']:null,$data['reason']??null,$data['reference']??null);
            } else throw new ApiError('Unknown seller payment action.',404);
            return ['id'=>$p->id,'revision'=>$p->revision,'payment_state'=>$p->payment_state];
        }
        $id=$this->identity->approved('buyer');
        if($action==='configuration') {
            $details=$workflow->platformInstructions();
            return ['account_name'=>$details['name'],'account_number'=>$details['number'],'instructions'=>['Transfer the exact amount to the displayed PocketFinds GCash account.','Upload your receipt. PocketFinds admin must verify payment before the seller prepares your order.']];
        }
        if(in_array($action,['order-payment','order-proof'],true)) {
            validateData($data,['order_id'=>'required|uuid']);
            $order=App\Models\Order::where('buyer_id',$id)->findOrFail($data['order_id']);
            $p=GcashPurchase::where('buyer_id',$id)->findOrFail($order->gcash_purchase_id);
            if($action==='order-proof')$this->handle('proof',['id'=>$p->id,'path'=>$data['path']??null,'submission_id'=>$data['submission_id']??null]);
            $p->refresh();$history=Illuminate\Support\Facades\DB::table('gcash_purchase_history')->where('purchase_id',$p->id)->orderBy('id')->get();
            $submitted=$history->where('event','proof_submitted')->last();$rejected=$history->whereIn('event',['admin_rejected','admin_short_payment'])->last();
            $status=match($p->payment_state){'order_placed','verified'=>'paid','proof_submitted'=>'pending_verification','underpaid'=>'underpaid','rejected'=>'rejected',default=>'pending'};
            return ['order_id'=>$order->id,'order_number'=>$order->order_number,'gcash_purchase_id'=>$p->id,'payment_state'=>$p->payment_state,'status'=>$status,'reference_required'=>false,
                'amount_due'=>number_format($p->payment_state==='underpaid'?max(0,(float)$p->amount_due-(float)$p->verified_amount):(float)$p->amount_due,2,'.',''),
                'can_submit'=>$order->status!=='cancelled' && in_array($p->payment_state,['awaiting_payment','rejected','underpaid'],true),'submitted_at'=>$submitted?->created_at,'rejection_reason'=>in_array($p->payment_state,['rejected','underpaid'],true)?$rejected?->reason:null,
                'configuration'=>['account_name'=>$p->instructions['name']??null,'account_number'=>$p->instructions['number']??null,'instructions'=>['Pay the exact amount to the displayed account.','Upload your receipt for '.($p->usesAdminReview()?'PocketFinds admin':'seller').' review. Receipt submission does not verify payment.']],
                'support_email'=>App\Models\Setting::current()->support_email];
        }
        if ($action==='list') {
            $purchases=GcashPurchase::where('buyer_id',$id)->latest()->limit(100)->get();
            $sellers=\App\Models\User::whereIn('id',$purchases->pluck('seller_id'))->get()->keyBy('id');
            return $purchases->map(function($p) use($workflow,$sellers) {
                if ($p->reservation_state==='initializing') $workflow->activate($p->id);
                $workflow->expire($p->id); $p->refresh();
                $seller=$sellers->get($p->seller_id);
                return ['id'=>$p->id,'review_flow'=>$p->usesAdminReview()?'admin':'seller','order_id'=>\App\Models\Order::where('gcash_purchase_id',$p->id)->where('buyer_id',$p->buyer_id)->value('id'),'seller_id'=>$p->seller_id,'seller_name'=>$seller?->business_name?:trim($seller?->given_names.' '.$seller?->last_name),'amount_due'=>$p->amount_due,'payment_state'=>$p->payment_state,'reservation_state'=>$p->reservation_state,
                    'instructions'=>$p->instructions,'revision'=>$p->revision,'expires_at'=>$p->expires_at?->toIso8601String(),
                    'can_upload_proof'=>in_array($p->payment_state,['awaiting_payment','rejected','underpaid'],true) && $p->reservation_state!=='initializing' && ($p->instructions!==null || $p->reservation_state==='expired'),
                    'can_report_paid'=>$p->reservation_state==='expired' && !in_array($p->payment_state,['verified','order_placed','manual_resolution'],true),
                    'refund_notice'=>in_array($p->payment_state,['manual_resolution'],true)?'Payment requires seller reconciliation. No refund has been recorded by this workflow.':null,
                    'placement'=>$workflow->placement($this->identity->user,$p->id)];
            })->all();
        }
        validateData($data,['id'=>'required|uuid']);
        $purchase=GcashPurchase::where('buyer_id',$id)->find($data['id']); ensure($purchase!==null,'Purchase not found.',404);
        if ($action==='report-paid') {
            validateData($data,['reason'=>'required|string|min:10|max:1000']);
            $workflow->reportPaid($this->identity->user,$purchase->id,$data['reason']);
            return ['reported'=>true,'message'=>'Payment reported for seller reconciliation. No refund is assumed.'];
        }
        if ($action==='place') {
            validateData($data,['revision'=>'required|integer']);
            $order=$workflow->place($this->identity->user,$purchase->id,$data['revision']);
            $saved=\Illuminate\Support\Facades\DB::table('mobile_order_quotes')->where('subject_id',$purchase->id)->first();
            if ($saved) MobileMarket::saveQuote($order->id,$id,$saved->checkout_id,json_decode($saved->snapshot,true));
            return ['id'=>$order->id,'order_number'=>$order->order_number];
        }
        if ($action==='proof') {
            validateData($data,['path'=>'required|string','submission_id'=>'required|uuid']);
            $source='payment-proofs/'.MobileMedia::path($data['path']);
            ensure(str_starts_with($data['path'],$id.'/') && Storage::disk('local')->exists($source),'Upload your receipt first.');
            (new MobileBuyer($this->identity))->native(['submission_id'=>$data['submission_id']],function($unused,$request)use($source,$purchase,$workflow){
                $request->files->set('receipt',new Illuminate\Http\UploadedFile(Storage::disk('local')->path($source),basename($source),null,null,true));
                return app(App\Http\Controllers\GcashPurchaseController::class)->proof($request,$purchase->id,$workflow);
            });
            return ['ok'=>true];
        }
        throw new ApiError('Unknown payment action.',404);
    }
}
