<?php

namespace App\Services;

use App\Models\{Order, PaymentMethod, Shipment, User};
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Shared by Laravel web controllers and the mobile API. Never transfers funds. */
class PlatformPayments
{
    public function available(): bool
    {
        return Schema::hasColumn('orders', 'platform_payment') && Schema::hasColumn('payment_verifications','can_resubmit')
            && Schema::hasTable('platform_payment_references') && Schema::hasTable('platform_payment_audit');
    }

    private function require(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['payment' => $message]);
    }

    public function configuration(): array
    {
        $path = config('platform_payments.qr_path');
        $disk = Storage::disk(config('platform_payments.qr_disk', 'local'));
        $this->require((bool) config('platform_payments.enabled') && is_string($path)
            && config('platform_payments.account_name') && config('platform_payments.account_number')
            && $disk->exists($path), 'Official PocketFinds GCash details are not configured. Choose another method or try later.');
        $content = $disk->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        $this->require(in_array($mime, ['image/jpeg', 'image/png'], true) && strlen($content) <= 5 * 1024 * 1024, 'The configured GCash QR image is unavailable.');
        return [
            'account_name' => config('platform_payments.account_name'),
            'account_number' => config('platform_payments.account_number'),
            'qr_data' => 'data:'.$mime.';base64,'.base64_encode($content),
            'support_email' => config('platform_payments.support_email'),
            'max_receipt_bytes' => 5 * 1024 * 1024,
            'instructions' => ['Save or scan the official QR using GCash.', 'Check the registered account name before sending.', 'Send the exact amount shown for this order.', 'Save your transaction screenshot and reference number, then tap Next.'],
        ];
    }

    public function initialize(Order $order): void
    {
        $this->configuration();
        $this->require(DB::transactionLevel() > 0 && $order->platform_payment && $order->inventory_reserved, 'Payment orders must reserve stock in the checkout transaction.');
        DB::table('payment_verifications')->insert([
            'id' => (string) Str::uuid(), 'order_id' => $order->id, 'buyer_id' => $order->buyer_id,
            'reference_number' => '', 'proof_path' => '', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(), 'submitted_at' => now(),
        ]);
        $this->audit($order->id, $order->buyer_id, 'payment_created', ['amount' => $order->total]);
    }

    public function detail(User $actor, string $id): array
    {
        $order = Order::findOrFail($id);
        abort_unless($actor->status === 'approved' && ($actor->is_admin || ($actor->account_type === 'buyer' && (int) $order->buyer_id === (int) $actor->id)), 403);
        $this->require((bool) $order->platform_payment, 'This order uses the existing payment workflow.');
        $p = DB::table('payment_verifications')->where('order_id', $id)->first();
        return [
            'order_id' => $id, 'order_number' => $order->order_number, 'amount_due' => $order->total,
            'order_status' => $order->status, 'status' => $p->status,
            'reference_number' => $p->reference_number,
            'submitted_at' => $p->submission_id ? \Carbon\Carbon::parse($p->submitted_at)->toIso8601String() : null,
            'verification_deadline' => $p->verification_deadline ? \Carbon\Carbon::parse($p->verification_deadline)->toIso8601String() : null,
            'reviewed_at' => $p->reviewed_at, 'rejection_reason' => $p->rejection_reason,
            'can_submit' => $order->status === 'pending_payment' && $p->status === 'pending'
                || $order->status === 'pending_payment_verification' && $p->status === 'rejected' && $p->can_resubmit,
            'can_resubmit' => (bool) $p->can_resubmit,
            'support_email' => config('platform_payments.support_email'),
        ];
    }

    public function submit(User $buyer, string $id, string $submission, string $reference, string $path, string $amount): array
    {
        abort_unless($buyer->status === 'approved' && $buyer->account_type === 'buyer' && !$buyer->is_admin, 403);
        $reference = trim($reference);
        $this->require((bool) preg_match('/^[0-9]{10,20}$/D', $reference), 'Enter the 10–20 digit GCash reference number.');
        $this->require(Str::isUuid($submission) && str_starts_with($path, $buyer->id.'/')
            && !preg_match('/(^|\/)\.{1,2}(\/|$)|[\\\\\x00-\x1f:]/', $path), 'Upload your own receipt first.');
        $this->require(Storage::disk('local')->exists('payment-proofs/'.$path), 'Upload your receipt first.');
        $content = Storage::disk('local')->get('payment-proofs/'.$path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        $this->require(strlen($content) > 0 && strlen($content) <= 5 * 1024 * 1024
            && in_array($mime, ['image/jpeg', 'image/png'], true) && @getimagesizefromstring($content) !== false, 'Use a valid JPG or PNG receipt up to 5 MB.');
        return DB::transaction(function () use ($buyer, $id, $submission, $reference, $path, $amount, $content) {
            $order = Order::lockFresh($id);
            abort_unless((int) $order->buyer_id === (int) $buyer->id && $order->platform_payment, 403);
            $p = DB::table('payment_verifications')->where('order_id', $id)->lockForUpdate()->first();
            if ($p->submission_id === $submission) {
                $this->require($p->reference_number === $reference && $p->proof_path === $path
                    && (int) round((float) $amount * 100) === (int) round((float) $p->amount_paid * 100), 'This submission identifier was already used for different evidence.');
                return $this->detail($buyer, $id);
            }
            $this->require(($order->status === 'pending_payment' && $p->status === 'pending')
                || ($order->status === 'pending_payment_verification' && $p->status === 'rejected' && $p->can_resubmit), 'A receipt is already under review, or this order cannot accept payment evidence.');
            $this->require(is_numeric($amount) && (int) round((float) $amount * 100) === (int) round((float) $order->total * 100), 'The amount paid must match the backend order total.');
            $this->require(!DB::table('payment_verifications')->where('submission_id',$submission)->where('order_id','!=',$id)->exists(),'This submission identifier belongs to another order.');
            $hash = hash('sha256', $reference);
            DB::table('platform_payment_references')->insertOrIgnore(['reference_hash'=>$hash,'order_id'=>$id,'created_at'=>now()]);
            $this->require(DB::table('platform_payment_references')->where('reference_hash',$hash)->value('order_id')===$id,'This reference was already used for another order.');
            $this->require(!DB::table('payment_verifications')->where('reference_hash', $hash)->where('order_id', '!=', $id)->exists(), 'This reference number was already submitted for another order.');
            DB::table('payment_verifications')->where('order_id', $id)->update([
                'status' => 'pending_verification', 'reference_number' => $reference, 'reference_hash' => $hash,
                'proof_path' => $path, 'submission_id' => $submission, 'amount_paid' => $order->total,
                'submitted_at' => now(), 'verification_deadline' => now()->addHours(max(1, (int) config('platform_payments.verification_hours'))),
                'can_resubmit' => false, 'rejection_reason' => null, 'reviewed_at' => null, 'reviewed_by' => null, 'updated_at' => now(),
            ]);
            $order->forceFill(['status' => 'pending_payment_verification'])->save();
            $order->logStatus('pending_payment_verification', $buyer->id, 'Receipt submitted; payment is not yet verified.');
            $this->audit($id, $buyer->id, 'receipt_submitted', ['submission_id' => $submission, 'proof_path'=>$path, 'reference_hash'=>$hash, 'sha256' => hash('sha256', $content), 'amount' => $order->total]);
            Order::notifyUser($buyer->id, 'Payment receipt submitted', 'PocketFinds admin is checking your transaction. Your order is not confirmed for fulfillment yet.', 'order_status', $id);
            foreach (User::where('is_admin', true)->where('status', 'approved')->pluck('id') as $admin)
                Order::notifyUser($admin, 'GCash receipt awaiting review', $order->order_number.' requires payment verification.', 'payment_review', $id);
            return $this->detail($buyer, $id);
        }, 3);
    }

    public function decide(User $admin, string $id, bool $approve, ?string $reason, bool $resubmit = false): array
    {
        abort_unless($admin->is_admin && $admin->status === 'approved', 403);
        return DB::transaction(function () use ($admin, $id, $approve, $reason, $resubmit) {
            $o = Order::lockFresh($id);
            $p = DB::table('payment_verifications')->where('order_id', $id)->lockForUpdate()->first();
            $this->require($o->platform_payment && in_array($o->status,['pending_payment_verification','cancelled'],true)
                && $p?->status === 'pending_verification', 'This payment is no longer awaiting verification.');
            $this->require($approve || mb_strlen(trim($reason ?? '')) >= 10, 'Explain why payment verification failed (at least 10 characters).');
            $this->require(!$approve || (int) round((float) $p->amount_paid * 100) === (int) round((float) $o->total * 100), 'Payment amount no longer matches the order.');
            DB::table('payment_verifications')->where('order_id', $id)->update([
                'status' => $approve ? 'paid' : 'rejected', 'rejection_reason' => $approve ? null : trim($reason),
                'can_resubmit' => !$approve && $resubmit, 'reviewed_at' => now(), 'reviewed_by' => $admin->id, 'updated_at' => now(),
            ]);
            if ($approve && $o->status!=='cancelled') {
                $o->forceFill(['status' => 'placed'])->save();
                $o->logStatus('payment_confirmed', $admin->id, 'PocketFinds admin verified the GCash transaction.');
                $o->logStatus('placed', $admin->id);
                Order::notifyUser($o->seller_id, 'Payment verified — order ready to process', $o->order_number.' is ready for fulfillment.', 'order_status', $id);
            }
            $this->audit($id, $admin->id, $approve ? 'payment_verified' : 'payment_rejected', ['reason' => $reason, 'resubmission_allowed' => !$approve && $resubmit]);
            Order::notifyUser($o->buyer_id, $approve ? 'Payment successfully verified' : 'Payment verification failed', $approve ? ($o->status==='cancelled'?'Your cancelled order payment was verified. Admin refund review is required.':'Your order can now be processed by the seller.') : trim($reason), 'order_status', $id);
            return $this->detail($admin, $id);
        }, 3);
    }

    public function guardOrder(Order $o): void
    {
        if (!$o->exists || !$o->getRawOriginal('platform_payment')) return;
        $this->require(!$o->isDirty(['total', 'items', 'buyer_id', 'seller_id', 'platform_payment']), 'Payment order totals and ownership cannot be changed after checkout.');
        if ($o->isDirty('status') && !in_array($o->status, ['pending_payment', 'pending_payment_verification', 'cancelled', 'refunded'], true)) {
            $this->require(DB::table('payment_verifications')->where('order_id', $o->id)->where('status', 'paid')->exists(), 'PocketFinds admin must verify payment before fulfillment.');
        }
    }

    public function refund(User $admin, string $id, string $reference): array
    {
        abort_unless($admin->is_admin && $admin->status === 'approved',403);
        return DB::transaction(function() use($admin,$id,$reference) {
            $o=Order::lockFresh($id);
            $p=DB::table('payment_verifications')->where('order_id',$id)->lockForUpdate()->first();
            $this->require($o->platform_payment && in_array($o->status,['cancelled','returned','refunded'],true) && $p?->status==='paid', 'Only a verified payment on a cancelled or returned order can be recorded as refunded.');
            $this->require(mb_strlen(trim($reference))>=5,'Record the actual refund transaction reference.');
            DB::table('payment_verifications')->where('order_id',$id)->update(['status'=>'refunded','refund_reference'=>trim($reference),'refunded_at'=>now(),'updated_at'=>now()]);
            $o->forceFill(['status'=>'refunded'])->save();
            $o->logStatus('refunded',$admin->id,'Admin recorded actual repayment.');
            $this->audit($id,$admin->id,'refund_recorded',['reference'=>$reference,'amount'=>$o->total]);
            Order::notifyUser($o->buyer_id,'Refund processed','PocketFinds admin recorded your refund. Reference: '.$reference,'order_status',$id);
            return $this->detail($admin,$id);
        },3);
    }

    public function guardShipment(Shipment $s): void
    {
        $o = $s->order_id ? Order::find($s->order_id) : null;
        if ($o?->platform_payment) $this->require(DB::table('payment_verifications')->where('order_id', $o->id)->where('status', 'paid')->exists(), 'Payment must be verified before assigning or fulfilling this order.');
    }

    public function audit(string $subject, ?int $actor, string $action, array $details): void
    {
        DB::table('platform_payment_audit')->insert(['id' => (string) Str::uuid(), 'subject_id' => $subject, 'actor_id' => $actor, 'action' => $action, 'details' => json_encode($details), 'created_at' => now()]);
    }

    public function expireUnsubmitted(): int
    {
        if(!$this->available()) return 0;
        $count=0;
        foreach(Order::where('platform_payment',true)->where('status','pending_payment')->where('created_at','<=',now()->subHours(max(1,(int)config('platform_payments.payment_hours'))))->get(['id']) as $candidate) {
            $count+=DB::transaction(function() use($candidate) {
                $o=Order::lockFresh($candidate->id);
                $p=DB::table('payment_verifications')->where('order_id',$o->id)->lockForUpdate()->first();
                if($o->status!=='pending_payment' || $p?->status!=='pending') return 0;
                $o->cancelAndRestock('Payment window expired',null,null,['pending_payment']);
                DB::table('payment_verifications')->where('order_id',$o->id)->update(['status'=>'expired','updated_at'=>now()]);
                $this->audit($o->id,null,'payment_expired',[]);
                Order::notifyUser($o->buyer_id,'Payment expired','The payment window expired before receipt submission. If you already transferred money, contact support for reconciliation.','order_status',$o->id);
                return 1;
            },3);
        }
        return $count;
    }
}
