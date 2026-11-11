<?php

namespace App\Http\Controllers;

use App\Models\AccountUpdateRequest;
use App\Models\CompanyVehicle;
use App\Models\Complaint;
use App\Models\GcashPurchase;
use App\Models\Message;
use App\Models\Order;
use App\Models\Policy;
use App\Models\Product;
use App\Models\User;
use App\Services\GcashWorkflow;
use App\Services\MediaLifecycle;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class GcashPurchaseController extends Controller
{
    private function reviewer(Request $r): bool
    {
        $user = $r->user()->fresh();

        return (bool) $user->is_admin && $user->status === 'approved';
    }

    private function reviewNavigation(Request $r): void
    {
        if (! $this->reviewer($r)) {
            return;
        }
        View::share([
            'pendingRegistrations' => User::where('status', 'pending')->where('is_admin', false)->where('account_type', '!=', 'rider')->where(fn ($q) => $q->where('logistics_role', '!=', 'hub_staff')->orWhereNull('logistics_role'))->count(),
            'openDisputes' => Complaint::whereIn('status', ['open', 'escalated'])->count(),
            'unreadMessages' => Message::where('receiver_id', $r->user()->id)->where('read', false)->count(),
            'pendingDocs' => AccountUpdateRequest::where('status', 'pending')->count(),
            'pendingProducts' => Product::where('status', 'pending')->count(),
            'pendingCompanyPolicies' => Policy::where('type', 'logistics_company_terms')->whereNotNull('pending_content')->count(),
            'pendingVehicles' => CompanyVehicle::where('platform_status', 'pending')->count(),
        ]);
    }

    public function index(Request $r, GcashWorkflow $flow)
    {
        $role = $r->user()->account_type;
        if ($role === 'buyer' && ! $this->reviewer($r)) {
            return redirect()->route('buyer.orders', ['payment' => 'gcash']);
        }
        if ($this->reviewer($r)) {
            $q = GcashPurchase::where('payment_state', '!=', 'awaiting_payment');
        } else {
            abort_unless(in_array($role, ['buyer', 'seller']), 403);
            $flow->eligible($r->user(), $role);
            $q = GcashPurchase::where($role.'_id', $r->user()->id);
        }
        if (! $this->reviewer($r) && $r->filled('contact')) {
            $q->where(($role === 'buyer' ? 'seller' : 'buyer').'_id', $r->integer('contact'));
        }

        if ($role === 'seller' && $r->filled('q')) {
            $r->validate(['q'=>'string|max:100']);
            $search='%'.addcslashes($r->string('q')->toString(), '%_\\').'%';
            $q->where(fn($query)=>$query->where('id','like',$search)->orWhere('snapshot','like',$search));
        }

        $this->reviewNavigation($r);

        $cancelledPurchaseIds = collect();
        $refundPurchaseIds = collect();
        if ($this->reviewer($r)) {
            $cancelledPurchaseIds = Order::where('status','cancelled')->whereNotNull('gcash_purchase_id')->pluck('gcash_purchase_id');
            if (Schema::hasTable('cancellation_refunds')) {
                $refundPurchaseIds = DB::table('cancellation_refunds as refund')->join('orders as refund_order','refund_order.id','=','refund.order_id')->where('refund_order.status','cancelled')->whereNotNull('refund_order.gcash_purchase_id')->pluck('refund_order.gcash_purchase_id');
            }
        }
        $paymentCounts = $this->reviewer($r) ? (clone $q)->whereNotIn('id',$cancelledPurchaseIds)->selectRaw('payment_state, count(*) as aggregate')->groupBy('payment_state')->pluck('aggregate','payment_state') : collect();
        if ($this->reviewer($r)) {
            $paymentCounts['refunds'] = (clone $q)->whereIn('id',$refundPurchaseIds)->count();
            $paymentCounts['cancelled'] = (clone $q)->whereIn('id',$cancelledPurchaseIds)->whereNotIn('id',$refundPurchaseIds)->count();
            $paymentCounts['rejected'] = ($paymentCounts['rejected'] ?? 0) + ($paymentCounts['manual_resolution'] ?? 0);
            $paymentCounts->forget('manual_resolution');
            $r->validate(['q'=>'nullable|string|max:100','period'=>'nullable|in:all,30d,6m,year','state'=>'nullable|in:all,awaiting_payment,proof_submitted,underpaid,rejected,order_placed,manual_resolution,verified,refunds,cancelled']);
            if (in_array($r->query('state'), ['awaiting_payment', 'manual_resolution'], true)) {
                return redirect()->route('gcash.index', array_replace($r->query(), ['state' => $r->query('state') === 'manual_resolution' ? 'rejected' : 'all']));
            }
            if ($r->query('state') === 'refunds') {
                $q->whereIn('id',$refundPurchaseIds);
            } elseif ($r->query('state') === 'cancelled') {
                $q->whereIn('id',$cancelledPurchaseIds)->whereNotIn('id',$refundPurchaseIds);
            } elseif ($r->query('state') === 'rejected') {
                $q->whereNotIn('id',$cancelledPurchaseIds)->whereIn('payment_state', ['rejected', 'manual_resolution']);
            } elseif ($r->filled('state') && $r->query('state') !== 'all') {
                $q->whereNotIn('id',$cancelledPurchaseIds)->where('payment_state',$r->query('state'));
            }
            if ($r->filled('q')) { $search='%'.addcslashes($r->query('q'),'%_\\').'%'; $q->where(fn($query)=>$query->where('id','like',$search)->orWhere('snapshot','like',$search)); }
            $q->when($r->query('period')==='30d',fn($query)=>$query->where('created_at','>=',now()->subDays(30)))
              ->when($r->query('period')==='6m',fn($query)=>$query->where('created_at','>=',now()->subMonths(6)))
              ->when($r->query('period')==='year',fn($query)=>$query->where('created_at','>=',now()->startOfYear()));
        }
        $platformSettings=$this->reviewer($r)?\App\Models\Setting::current():null;
        $platformRefunds=collect();
        if($this->reviewer($r)&&\Illuminate\Support\Facades\Schema::hasColumn('gcash_purchases','review_flow')&&\Illuminate\Support\Facades\Schema::hasTable('cancellation_refunds'))
            $platformRefunds=DB::table('cancellation_refunds as r')->join('orders as o','o.id','=','r.order_id')->join('gcash_purchases as p','p.id','=','o.gcash_purchase_id')->where('p.review_flow','admin')->where('r.status','pending')->select('r.*','o.order_number')->get();
        if ($this->reviewer($r) && Schema::hasTable('gcash_purchase_history')) {
            $q->select('gcash_purchases.*')->selectRaw('COALESCE((SELECT MAX(payment_activity.created_at) FROM gcash_purchase_history AS payment_activity WHERE payment_activity.purchase_id = gcash_purchases.id), gcash_purchases.updated_at, gcash_purchases.created_at) AS latest_payment_activity')
                ->orderByDesc('latest_payment_activity');
        }
        return view('gcash.index', ['purchases' => $q->latest('gcash_purchases.created_at')->orderByDesc('gcash_purchases.id')->paginate(20),'platformSettings'=>$platformSettings,'platformRefunds'=>$platformRefunds,'paymentCounts'=>$paymentCounts]);
    }

    public function refundOverpayment(Request $request, string $id, GcashWorkflow $flow)
    {
        $flow->adminReviewer($request->user());
        $data = $request->validate(['repayment_reference'=>'required|string|min:6|max:100', 'confirm_repaid'=>'required|accepted', 'refund_proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        DB::transaction(function () use ($request, $id, $flow, $data) {
            $flow->refundOverpayment($request->user(), $id, $data['repayment_reference']);
            $this->recordAdminEvidence($request, $id, 'refund_proof', 'admin_refund_proof');
        });
        return back()->with('success', 'Excess payment refund recorded.');
    }

    private function recordAdminEvidence(Request $request, string $id, string $field, string $event): void
    {
        $file = $request->file($field);
        $path = app(MediaLifecycle::class)->store($file, 'gcash-proofs', 'local');
        DB::table('gcash_purchase_history')->insert(['purchase_id'=>$id, 'actor_id'=>$request->user()->id, 'event'=>$event, 'before'=>json_encode(['path'=>$path, 'mime'=>$file->getMimeType(), 'amount'=>$request->input('amount'), 'reference'=>$request->input('reference') ?? $request->input('repayment_reference')]), 'after'=>'{}', 'reason'=>'Admin uploaded GCash transaction evidence.', 'created_at'=>now()]);
    }

    public function adminEvidence(Request $request, string $id, string $history, GcashWorkflow $flow)
    {
        $purchase = GcashPurchase::findOrFail($id);
        abort_unless($this->reviewer($request) || (int)$purchase->buyer_id === (int)$request->user()->id, 403);
        $row = DB::table('gcash_purchase_history')->where('purchase_id',$id)->where('id',$history)->whereIn('event',['admin_received_proof','admin_refund_proof','admin_rejection_refund_proof','admin_cancellation_refund_proof'])->first();
        abort_unless($row,404);
        $metadata = json_decode($row->before,true);
        abort_unless(Storage::disk('local')->exists($metadata['path']),404);
        return response()->file(Storage::disk('local')->path($metadata['path']),['Content-Type'=>$metadata['mime'], 'Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff']);
    }

    public function refundDetails(Request $request, string $id, GcashWorkflow $flow)
    {
        $flow->eligible($request->user(), 'buyer');
        $request->merge(['number'=>'0'.ltrim((string)$request->input('number'),'0')]);
        $data=$request->validate(['name'=>'required|string|max:150','number'=>['required','regex:/^09[0-9]{9}$/D']]);
        DB::transaction(function () use ($request,$id,$data) {
            $p=GcashPurchase::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$p->buyer_id === (int)$request->user()->id && $p->usesAdminReview(),403);
            $history=DB::table('gcash_purchase_history')->where('purchase_id',$id);
            abort_unless((clone $history)->where('event','overpayment_pending')->exists() && !(clone $history)->where('event','overpayment_refunded')->exists(),409,'No excess payment refund is pending.');
            abort_if((clone $history)->where('event','refund_details_submitted')->exists(),409,'Refund details have already been sent. Contact support if a correction is needed.');
            DB::table('gcash_purchase_history')->insert(['purchase_id'=>$id,'actor_id'=>$request->user()->id,'event'=>'refund_details_submitted','before'=>json_encode($data),'after'=>'{}','reason'=>'Buyer supplied GCash details for the excess payment refund.','created_at'=>now()]);
            foreach(User::where('is_admin',true)->where('status','approved')->pluck('id') as $adminId) \App\Models\Order::notifyUser($adminId,'GCash refund details received','The buyer supplied refund GCash details for purchase '.$id.'.','gcash_purchase',$id);
        });
        return back()->with('success','Refund GCash details sent to admin.');
    }

    public function remindUnderpayment(Request $request, string $id, GcashWorkflow $flow)
    {
        $flow->adminReviewer($request->user());
        DB::transaction(function () use ($id) {
            $p=GcashPurchase::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($p->usesAdminReview() && $p->payment_state==='underpaid',409,'This payment is not awaiting a top-up.');
            $remaining=max(0,(float)$p->amount_due-(float)$p->verified_amount);
            \App\Models\Order::notifyUser($p->buyer_id,'GCash balance reminder','Please pay the remaining PHP '.number_format($remaining,2).' and upload a new GCash receipt.','gcash_purchase',$id);
        });
        return back()->with('success','Payment balance reminder sent to the buyer.');
    }

    public function platformAccount(Request $request,GcashWorkflow $flow)
    {
        $flow->adminReviewer($request->user());
        $request->merge(['platform_gcash_number'=>'0'.ltrim((string)$request->input('platform_gcash_number'),'0')]);
        abort_unless(\Illuminate\Support\Facades\Schema::hasColumn('settings','platform_gcash_number'),503,'Install the platform GCash migration first.');
        $data=$request->validate(['platform_gcash_name'=>'required|string|max:150','platform_gcash_number'=>['required','regex:/^09[0-9]{9}$/D']]);
        \App\Models\Setting::current()->update($data+['updated_by'=>$request->user()->id]);
        return back()->with('success','Platform GCash details saved. Existing purchase destinations were kept unchanged.');
    }

    public function refreshPrices(Request $r, GcashWorkflow $flow)
    {
        $data = $r->validate(['items' => 'required|array|min:1|max:100', 'items.*' => 'required|string|distinct', 'acknowledge' => 'sometimes|accepted', 'acknowledgement' => 'required_with:acknowledge|string']);

        return response()->json($flow->refreshCartPrices($r->user(), $data['items'], $r->has('acknowledge') ? $data['acknowledgement'] : null))->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $r, GcashWorkflow $flow)
    {
        if(\App\Services\BuyerCommerce::installed()) return app(CommerceController::class)->checkout($r);
        $d = $r->validate(['items' => 'required|array|min:1|max:100', 'items.*' => 'required|string|distinct', 'payment_method' => 'required|integer', 'checkout_id' => 'required|uuid', 'voucher_code' => 'nullable|string|max:30', 'delivery_address_id' => 'nullable|integer', 'buyer_note' => 'nullable|string|max:500']);
        $rows = $flow->create($r->user(), $d['items'], $d['payment_method'], $d['checkout_id'], $d['voucher_code'] ?? null, $d['delivery_address_id'] ?? null, $d['buyer_note'] ?? null);

        return response()->json(['committed' => true, 'initializing' => collect($rows)->contains(fn ($p) => $p->fresh()->reservation_state === 'initializing'), 'purchases' => array_map(fn ($p) => $p->fresh(), $rows)])->header('Cache-Control', 'private, no-store');
    }

    private function participant(Request $r, GcashPurchase $p, GcashWorkflow $flow): void
    {
        if ($this->reviewer($r)) {
            return;
        }
        $role = $r->user()->account_type;
        abort_unless(in_array($role, ['buyer', 'seller']), 403);
        $flow->eligible($r->user(), $role);
        abort_unless((int) $p->{$role.'_id'} === (int) $r->user()->id, 403);
    }

    public function show(Request $r, string $id, GcashWorkflow $flow)
    {
        $p = GcashPurchase::findOrFail($id);
        $this->participant($r, $p, $flow);
        if ($p->reservation_state === 'initializing') {
            $flow->activate($id);
        }
        $flow->expire($id);
        $p->refresh();

        $placement = !$this->reviewer($r) && $r->user()->account_type === 'buyer' ? $flow->placement($r->user(), $id) : ['eligible' => false, 'reason' => null];
        $proofs = DB::table('gcash_proofs')->where('purchase_id', $id)->get(['id', 'mime', 'size', 'created_at']);
        $history = DB::table('gcash_purchase_history')->where('purchase_id', $id)->orderByDesc('id')->paginate(20);
        $paymentHistory = in_array($r->user()->account_type, ['buyer', 'seller'], true) && $r->boolean('embedded') && !$r->expectsJson()
            ? DB::table('gcash_purchase_history')->where('purchase_id',$id)->orderBy('id')->get() : collect();
        $actors = User::whereIn('id', $history->getCollection()->concat($paymentHistory)->pluck('actor_id')->filter()->unique())
            ->get(['id','given_names','last_name','username','business_name'])->keyBy('id');
        foreach ($history->getCollection()->concat($paymentHistory) as $event) {
            $actor = $actors->get($event->actor_id);
            $event->actor_name = !$event->actor_id ? 'System' : ($actor
                ? (trim(($actor->given_names ?? '').' '.($actor->last_name ?? '')) ?: $actor->business_name ?: $actor->username)
                : 'Former account');
        }
        if (! $r->expectsJson()) {
            $this->reviewNavigation($r);

            if ($r->user()->account_type === 'buyer' && ! $r->boolean('embedded')) {
                return redirect()->route('buyer.orders', ['payment' => 'gcash', 'payment_purchase' => $id]);
            }

            return response()->view('gcash.show', compact('p', 'proofs', 'history', 'paymentHistory', 'placement'))->header('Cache-Control', 'private, no-store');
        }

        return response()->json(['purchase' => $p, 'proofs' => DB::table('gcash_proofs')->where('purchase_id', $id)->get(['id', 'mime', 'size', 'created_at']), 'history' => DB::table('gcash_purchase_history')->where('purchase_id', $id)->orderByDesc('id')->paginate(20)])->header('Cache-Control', 'private, no-store');
    }

    public function instructions(Request $r, string $id, GcashWorkflow $flow)
    {
        $r->merge(['number'=>'0'.ltrim((string)$r->input('number'),'0')]);
        $d = $r->validate(['revision' => 'required|integer|min:0', 'name' => 'required|string|max:150', 'number' => 'required|string|regex:/^09[0-9]{9}$/D'], ['number.regex' => 'Enter a valid 11-digit Philippine mobile number starting with 09.']);

        return response()->json(['purchase' => $flow->instructions($r->user(), $id, $d['revision'], $d['name'], $d['number'])]);
    }

    public function proof(Request $r, string $id, GcashWorkflow $flow)
    {
        $p = GcashPurchase::findOrFail($id);
        $flow->eligible($r->user(), 'buyer');
        abort_unless((int) $p->buyer_id === (int) $r->user()->id, 403);
        $d = $r->validate(['submission_id' => 'required|uuid', 'receipt' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        $file = $r->file('receipt');
        $hash = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($r, $id, $flow, $d, $file, $hash) {
            $old = DB::table('gcash_proofs')->where('purchase_id', $id)->where('submission_id', $d['submission_id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->content_hash, $hash), 409, 'This retry identifier belongs to another receipt.');

                return response()->json(['purchase' => GcashPurchase::findOrFail($id)]);
            }
            $path = app(MediaLifecycle::class)->store($file, 'gcash-proofs', 'local');
            $p = $flow->proof($r->user(), $id, $d['submission_id'], $path, $file->getMimeType(), $file->getSize(), $hash);
            // A competing request may have recorded this nonce while this upload was processing.
            $saved = DB::table('gcash_proofs')->where('purchase_id', $id)->where('submission_id', $d['submission_id'])->lockForUpdate()->first();
            if ($saved->path !== $path) {
                app(MediaLifecycle::class)->deleteAfterCommit($path, 'local');
            }

            return response()->json(['purchase' => $p]);
        });
    }

    public function receipt(Request $r, string $id, string $proof, GcashWorkflow $flow)
    {
        $p = GcashPurchase::findOrFail($id);
        $this->participant($r, $p, $flow);
        $row = DB::table('gcash_proofs')->where('purchase_id', $id)->where('id', $proof)->first();
        abort_unless($row, 404);
        abort_unless(Storage::disk('local')->exists($row->path), 404);

        return response()->file(Storage::disk('local')->path($row->path), ['Content-Type' => $row->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }

    public function reportPaid(Request $r, string $id, GcashWorkflow $flow)
    {
        $d = $r->validate(['reason' => 'required|string|max:1000']);

        return response()->json(['purchase' => $flow->reportPaid($r->user(), $id, $d['reason'])]);
    }

    public function decide(Request $r, string $id, GcashWorkflow $flow)
    {
        $purchase = GcashPurchase::findOrFail($id);
        $underpaid = $purchase->usesAdminReview() && $r->string('decision')->toString() === 'underpaid';
        $needsEvidence = $purchase->usesAdminReview() && ($r->boolean('accept') || $underpaid);
        $refundOnRejection = $purchase->usesAdminReview() && !$r->boolean('accept') && $r->boolean('payment_returned');
        if ($purchase->usesAdminReview()) $flow->adminReviewer($r->user());
        if ($needsEvidence) $r->validate(['received_proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        if ($refundOnRejection) {
            abort_unless($r->string('decision')->toString() === 'other', 422, 'Refund proof is available only when the review outcome is Other.');
            $r->validate(['refund_proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        }
        $rules = ['revision' => 'required|integer|min:0', 'accept' => 'required|boolean'];
        if ($purchase->usesAdminReview()) {
            $rules += ['amount' => 'nullable|decimal:0,2|min:0', 'reference' => 'nullable|string|max:64', 'decision' => 'required_if:accept,0|in:not_received,underpaid,other', 'reason' => 'nullable|string|max:1000', 'payment_returned'=>'nullable|in:1'];
        } else {
            $rules += ['amount' => 'required_if:accept,1|nullable|decimal:0,2|min:0', 'reference' => 'required_if:accept,1|nullable|string|max:64', 'reason' => 'required_if:accept,0|nullable|string|max:1000'];
        }
        $d = $r->validate($rules);
        try {
            return DB::transaction(function () use ($r,$id,$flow,$d,$needsEvidence,$underpaid,$refundOnRejection,$purchase) {
                $accepted = (bool) $d['accept'];
                $reason = $d['reason'] ?? null;
                if (!$accepted && blank($reason)) $reason = $underpaid
                    ? 'The amount received is less than the order total. Please pay the remaining balance and submit a top-up receipt.'
                    : str_replace('_', ' ', $d['decision'] ?? 'payment rejected');
                if ($refundOnRejection) $reason = mb_substr(trim((string)$reason).' Payment returned to the buyer; refund proof is attached.', 0, 1000);
                $result = $underpaid
                    ? $flow->notifyBuyerOfShortPayment($r->user(), $id, $d['revision'], (string) $reason)
                    : $flow->decide($r->user(), $id, $d['revision'], $accepted, $purchase->usesAdminReview() && $accepted ? (string) $purchase->amount_due : ($d['amount'] ?? null), $reason, $d['reference'] ?? null);
                if ($needsEvidence) $this->recordAdminEvidence($r,$id,'received_proof','admin_received_proof');
                if ($refundOnRejection) $this->recordAdminEvidence($r,$id,'refund_proof','admin_rejection_refund_proof');
                return response()->json(['purchase'=>$result]);
            });
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[1] ?? null, [1062, 19])) {
                abort(409, 'This payment reference was already recorded.');
            }throw $e;
        }
    }

    public function resolve(Request $r, string $id, GcashWorkflow $flow)
    {
        $d = $r->validate(['revision' => 'required|integer|min:0', 'reacquire' => 'required|boolean', 'amount' => 'required|decimal:0,2|min:0', 'reference' => 'required|string|max:64', 'reason' => 'required|string|max:1000']);

        return response()->json(['purchase' => $flow->resolve($r->user(), $id, $d['revision'], $d['amount'], $d['reason'], (bool) $d['reacquire'], $d['reference'])]);
    }

    public function place(Request $r, string $id, GcashWorkflow $flow)
    {
        $d = $r->validate(['revision' => 'required|integer|min:0']);

        return response()->json(['order' => $flow->place($r->user(), $id, $d['revision'])]);
    }

    public function requestResolution(Request $r, string $id, GcashWorkflow $flow)
    {
        $d = $r->validate(['revision' => 'required|integer|min:0', 'reason' => 'required|string|max:1000']);

        return response()->json(['purchase' => $flow->requestResolution($r->user(), $id, $d['revision'], $d['reason'])]);
    }

    public function abandon(Request $r, string $id, GcashWorkflow $flow)
    {
        $d = $r->validate(['revision' => 'required|integer|min:0']);

        return response()->json(['purchase' => $flow->abandon($r->user(), $id, $d['revision'])]);
    }
}
