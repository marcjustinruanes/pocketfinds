<?php

namespace App\Http\Controllers;

use App\Models\AccountUpdateRequest;
use App\Models\CompanyVehicle;
use App\Models\Complaint;
use App\Models\GcashPurchase;
use App\Models\Message;
use App\Models\Policy;
use App\Models\Product;
use App\Models\User;
use App\Services\GcashWorkflow;
use App\Services\MediaLifecycle;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class GcashPurchaseController extends Controller
{
    private function reviewer(Request $r): bool
    {
        $user = $r->user()->fresh();

        return $user->account_type === 'admin' && $user->is_admin && $user->status === 'approved';
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
        if ($this->reviewer($r)) {
            $q = GcashPurchase::query();
        } else {
            abort_unless(in_array($role, ['buyer', 'seller']), 403);
            $flow->eligible($r->user(), $role);
            $q = GcashPurchase::where($role.'_id', $r->user()->id);
        }
        if (! $this->reviewer($r) && $r->filled('contact')) {
            $q->where(($role === 'buyer' ? 'seller' : 'buyer').'_id', $r->integer('contact'));
        }

        $this->reviewNavigation($r);

        return view('gcash.index', ['purchases' => $q->latest()->paginate(20)]);
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

        $placement = $r->user()->account_type === 'buyer' ? $flow->placement($r->user(), $id) : ['eligible' => false, 'reason' => null];
        $proofs = DB::table('gcash_proofs')->where('purchase_id', $id)->get(['id', 'mime', 'size', 'created_at']);
        $history = DB::table('gcash_purchase_history')->where('purchase_id', $id)->orderByDesc('id')->paginate(20);
        if (! $r->expectsJson()) {
            $this->reviewNavigation($r);

            return response()->view('gcash.show', compact('p', 'proofs', 'history', 'placement'))->header('Cache-Control', 'private, no-store');
        }

        return response()->json(['purchase' => $p, 'proofs' => DB::table('gcash_proofs')->where('purchase_id', $id)->get(['id', 'mime', 'size', 'created_at']), 'history' => DB::table('gcash_purchase_history')->where('purchase_id', $id)->orderByDesc('id')->paginate(20)])->header('Cache-Control', 'private, no-store');
    }

    public function instructions(Request $r, string $id, GcashWorkflow $flow)
    {
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
        $d = $r->validate(['revision' => 'required|integer|min:0', 'accept' => 'required|boolean', 'amount' => 'required_if:accept,1|nullable|decimal:0,2|min:0', 'reference' => 'required_if:accept,1|nullable|string|max:64', 'reason' => 'required_if:accept,0|nullable|string|max:1000']);
        try {
            return response()->json(['purchase' => $flow->decide($r->user(), $id, $d['revision'], (bool) $d['accept'], $d['amount'] ?? null, $d['reason'] ?? null, $d['reference'] ?? null)]);
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
