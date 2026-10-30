<?php

namespace App\Services;

use App\Models\LogisticsHub;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LogisticsCashDesk
{
    public function installed(): bool
    {
        return Schema::hasTable('logistics_cash_movements') && Schema::hasTable('logistics_cash_allocations');
    }

    public function actor(User $user): User
    {
        abort_unless($this->installed(), 503, 'The Cash Desk migration is required.');
        $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        abort_unless($user->status === 'approved' && ! $user->is_admin && filled($user->business_name)
            && (($user->account_type === 'rider' && ! $user->is_logistics)
                || ($user->account_type === 'logistics' && $user->is_logistics
                    && ($user->isHubStaff() || $user->isLogisticsAdmin()))), 403);
        return $user;
    }

    public function scope($query, User $actor)
    {
        $query->where('company_name', $actor->business_name);
        if ($actor->account_type === 'rider') {
            $query->where('kind', 'remittance')->where('actor_id', $actor->id);
        } elseif ($actor->isHubStaff()) {
            $this->hub($actor->logistics_hub_id, $actor->business_name);
            $query->where(fn ($q) => $q->where('hub_id', $actor->logistics_hub_id)->orWhere('destination_hub_id', $actor->logistics_hub_id));
        }
        return $query;
    }

    private function hub(?int $id, string $company): object
    {
        $hub = DB::table('logistics_hubs')->where('id', $id)->lockForUpdate()->first();
        abort_unless($hub && $hub->company_name === $company && $hub->is_operational, 403, 'An operational hub in your company is required.');
        return $hub;
    }

    private function staff(User $actor, int $hub): void
    {
        abort_unless($actor->isHubStaff() && (int) $actor->logistics_hub_id === $hub, 403, 'Only assigned Hub Staff can count or release this cash.');
        $this->hub($hub, $actor->business_name);
    }

    public function amount(string $value): int
    {
        Validator::make(['amount' => $value], ['amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D']])->validate();
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public function balances(object $collection, ?bool $lock = null): array
    {
        $rows = DB::table('logistics_cash_allocations as a')->join('logistics_cash_handovers as h', 'h.id', '=', 'a.handover_id')
            ->where('a.collection_id', $collection->id)->when($lock ?? DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get(['a.*', 'h.status']);
        $verified = (int) $rows->sum('verified_cents');
        $reserved = (int) $rows->where('status', 'submitted')->sum('expected_cents');
        return ['verified' => $verified, 'reserved' => $reserved, 'outstanding' => (int) $collection->amount_cents - $verified,
            'available' => (int) $collection->amount_cents - $verified - $reserved];
    }

    public function dueAt(object $collection): CarbonImmutable
    {
        $cutoff = DB::table('logistics_finance_settings')->where('company_name', $collection->company_name)->value('remittance_cutoff') ?? '23:59';
        // recorded_at is stored in the application timezone; compare in the approved business timezone.
        return CarbonImmutable::parse($collection->recorded_at, config('app.timezone'))->setTimezone('Asia/Manila')->setTimeFromTimeString($cutoff);
    }

    private function replay(string $company, string $key, string $hash): ?object
    {
        $row = DB::table('logistics_cash_handovers')->where('company_name', $company)->where('idempotency_key', $key)->lockForUpdate()->first();
        if ($row) {
            abort_unless(hash_equals($row->request_hash, $hash), 409, 'This submission key was used with different details.');
        }
        return $row;
    }

    public function submit(User $actor, array $input): object
    {
        return $this->submitFor($actor,$input);
    }

    public function submitRecovery(User $actor,array $input): object
    {
        abort_unless(\Illuminate\Support\Facades\Schema::hasColumn('logistics_cash_handovers','submitted_by'),503,'Install the operational recovery migration.');
        $d=Validator::make($input,['rider_id'=>'required|integer','reason'=>'required|string|min:3|max:1000','reference'=>'required|string|min:3|max:255'])->validate();
        return $this->submitFor($actor,$input,(int)$d['rider_id'],$d['reason']."\nCustody reference: ".$d['reference']);
    }

    private function submitFor(User $actor,array $input,?int $holderId=null,?string $recoveryReason=null): object
    {
        $data = Validator::make($input, ['key' => 'required|uuid', 'allocations' => 'required|array|min:1|max:100',
            'allocations.*' => 'required|string|max:20'])->validate();
        $amounts = [];
        foreach ($data['allocations'] as $id => $value) {
            abort_unless(Str::isUuid($id), 422);
            $cents = $this->amount($value);
            if ($cents > 0) $amounts[$id] = $cents;
        }
        abort_unless($amounts, 422, 'Select at least one collection amount.');
        ksort($amounts);
        return DB::transaction(function () use ($actor, $data, $amounts,$holderId,$recoveryReason) {
            if($holderId) {
                $hubIds=DB::table('logistics_financial_transactions')->whereIn('id',array_keys($amounts))->pluck('hub_id')->filter()->all();
                GcashLocks::operation([],[],[$actor->id,$holderId],$hubIds);
            }
            $actor = $this->actor($actor);
            if($holderId) {
                abort_unless($actor->isHubStaff(),403,'Only assigned Hub Staff may prepare recovery remittances.');
                $holder=User::whereKey($holderId)->lockForUpdate()->firstOrFail();
                abort_unless($holder->account_type==='rider' && ! $holder->is_admin && ! $holder->is_logistics && $holder->status==='suspended' && $holder->business_name===$actor->business_name,403);
            } else { abort_unless($actor->account_type==='rider',403);$holder=$actor; }
            $hash = hash('sha256', json_encode($holderId ? ['recovery_remittance',$holder->id,$actor->id,$amounts,$recoveryReason] : ['remittance', $actor->id, $amounts]));
            if ($row = $this->replay($actor->business_name, $data['key'], $hash)) return $row;
            $collections = DB::table('logistics_financial_transactions')->whereIn('id', array_keys($amounts))->orderBy('id')->lockForUpdate()->get();
            abort_unless($collections->count() === count($amounts) && $collections->pluck('hub_id')->unique()->count() === 1, 422, 'Submit collections for one assigned hub at a time.');
            $hubId = (int) $collections->first()->hub_id;
            $this->hub($hubId, $actor->business_name);
            if($holderId)$this->staff($actor,$hubId);
            foreach ($collections as $c) {
                abort_unless($c->company_name === $actor->business_name && (int) $c->actor_id === (int) $holder->id
                    && $c->kind === 'cod_collection' && $c->status === 'recorded', 403);
                abort_unless($amounts[$c->id] <= $this->balances($c)['available'], 409, 'A selected amount is already submitted or verified. Refresh your balances.');
            }
            $id = (string) Str::uuid();
            DB::table('logistics_cash_handovers')->insert(['id' => $id, 'company_name' => $actor->business_name, 'kind' => 'remittance',
                'hub_id' => $hubId, 'actor_id' => $holder->id, 'expected_cents' => array_sum($amounts), 'status' => 'submitted',
                'idempotency_key' => $data['key'], 'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            if(\Illuminate\Support\Facades\Schema::hasColumn('logistics_cash_handovers','submitted_by'))DB::table('logistics_cash_handovers')->where('id',$id)->update(['submitted_by'=>$actor->id,'recovery_reason'=>$recoveryReason]);
            foreach ($amounts as $collection => $amount) {
                DB::table('logistics_cash_allocations')->insert(['handover_id' => $id, 'collection_id' => $collection, 'expected_cents' => $amount]);
            }
            $this->event($actor, 'remittance_submitted', $id, ['allocations' => $amounts,'cash_holder_id'=>$holder->id,'submitted_by'=>$actor->id,'recovery_reason'=>$recoveryReason]);
            return DB::table('logistics_cash_handovers')->where('id', $id)->first();
        }, 3);
    }

    public function count(User $actor, string $id, array $input): void
    {
        $data = Validator::make($input, ['counted' => 'required|string|max:20', 'allocations' => 'required|array|max:100',
            'allocations.*' => 'required|string|max:20', 'notes' => 'nullable|string|max:2000'])->validate();
        $counted = $this->amount($data['counted']);
        $allocated = array_map(fn ($v) => $this->amount($v), $data['allocations']);
        DB::transaction(function () use ($actor, $id, $data, $counted, $allocated) {
            $actor = $this->actor($actor);
            $h = $this->scope(DB::table('logistics_cash_handovers'), $actor)->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($h->kind === 'remittance', 422);
            // Serialize collection allocations in the same sorted order as submission.
            $rows = DB::table('logistics_cash_allocations')->where('handover_id', $id)->orderBy('collection_id')->lockForUpdate()->get();
            $collections = DB::table('logistics_financial_transactions')->whereIn('id', $rows->pluck('collection_id'))->orderBy('id')->lockForUpdate()->get();
            $this->staff($actor, (int) $h->hub_id);
            $expectedKeys = $rows->pluck('collection_id')->sort()->values()->all();
            $actualKeys = array_keys($allocated); sort($actualKeys);
            abort_unless($expectedKeys === $actualKeys, 422, 'Allocate only to the collections in this remittance.');
            abort_unless(array_sum($allocated) === min($counted, (int) $h->expected_cents), 422, 'Allocate the cash received up to the expected amount. Overages stay unallocated.');
            foreach ($rows as $row) abort_unless($allocated[$row->collection_id] <= (int) $row->expected_cents, 422, 'An allocation exceeds its submitted amount.');
            $notes = trim($data['notes'] ?? '');
            abort_if($counted !== (int) $h->expected_cents && $notes === '', 422, 'Document the cash discrepancy.');
            if ($h->status !== 'submitted') {
                abort_unless((int) $h->counted_by === (int) $actor->id && (int) $h->counted_cents === $counted && (string) $h->notes === $notes
                    && $rows->every(fn ($r) => (int) $r->verified_cents === $allocated[$r->collection_id]), 409, 'This remittance has already been counted.');
                return;
            }
            foreach ($rows as $row) {
                DB::table('logistics_cash_allocations')->where('id', $row->id)->update(['verified_cents' => $allocated[$row->collection_id]]);
            }
            DB::table('logistics_cash_handovers')->where('id', $id)->update(['counted_cents' => $counted, 'counted_by' => $actor->id,
                'counted_at' => now(), 'notes' => $notes, 'status' => $counted === (int) $h->expected_cents ? 'verified' : 'discrepancy', 'updated_at' => now()]);
            foreach ($collections as $c) {
                $balance = $this->balances($c);
                abort_if($balance['verified'] > (int) $c->amount_cents, 409, 'Collection allocation would exceed its recorded amount.');
                DB::table('logistics_financial_transactions')->where('id', $c->id)->update(['verification_state' => $balance['outstanding'] === 0 ? 'verified' : ($balance['verified'] > 0 ? 'partial' : 'pending'), 'updated_at' => now()]);
            }
            $this->movement($actor, $h, (int) $h->hub_id, 'cash_received', $counted);
            $this->event($actor, 'remittance_counted', $id, ['counted_cents' => $counted, 'allocations' => $allocated, 'notes' => $notes]);
        }, 3);
    }

    public function custody(int $hub): int
    {
        return (int) DB::table('logistics_cash_movements')->where('hub_id', $hub)
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get(['delta_cents'])->sum('delta_cents');
    }

    public function withdraw(User $actor, string $id): void
    {
        DB::transaction(function () use ($actor, $id) {
            $actor = $this->actor($actor);
            abort_unless($actor->account_type === 'rider', 403);
            $h = $this->scope(DB::table('logistics_cash_handovers'), $actor)->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_if(!empty($h->recovery_reason),403,'A Staff recovery submission cannot be withdrawn as a rider submission.');
            if ($h->status === 'withdrawn') return;
            abort_unless($h->kind === 'remittance' && $h->status === 'submitted', 409, 'Counted cash cannot be withdrawn.');
            // Lock collections before releasing their reservations, matching submit/count.
            $ids = DB::table('logistics_cash_allocations')->where('handover_id', $id)->pluck('collection_id');
            DB::table('logistics_financial_transactions')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            DB::table('logistics_cash_handovers')->where('id', $id)->update(['status' => 'withdrawn', 'updated_at' => now()]);
            $this->event($actor, 'remittance_withdrawn', $id, ['cash_moved' => false]);
        }, 3);
    }

    public function authorizeTransfer(User $actor, array $input): object
    {
        $data = Validator::make($input, ['key' => 'required|uuid', 'hub_id' => 'required|integer', 'destination_hub_id' => 'required|integer|different:hub_id',
            'amount' => 'required|string|max:20', 'notes' => 'required|string|max:2000'])->validate();
        $amount = $this->amount($data['amount']); abort_unless($amount > 0, 422);
        return DB::transaction(function () use ($actor, $data, $amount) {
            $actor = $this->actor($actor); app(LogisticsFinance::class)->admin($actor);
            $ids = [(int) $data['hub_id'], (int) $data['destination_hub_id']]; sort($ids);
            foreach ($ids as $hub) $this->hub($hub, $actor->business_name);
            $hash = hash('sha256', json_encode(['transfer', $actor->id, (int) $data['hub_id'], (int) $data['destination_hub_id'], $amount, $data['notes']]));
            if ($row = $this->replay($actor->business_name, $data['key'], $hash)) return $row;
            $id = (string) Str::uuid();
            DB::table('logistics_cash_handovers')->insert(['id' => $id, 'company_name' => $actor->business_name, 'kind' => 'transfer',
                'hub_id' => $data['hub_id'], 'destination_hub_id' => $data['destination_hub_id'], 'actor_id' => $actor->id,
                'expected_cents' => $amount, 'notes' => $data['notes'], 'status' => 'authorized', 'idempotency_key' => $data['key'],
                'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $this->event($actor, 'cash_transfer_authorized', $id, $data);
            return DB::table('logistics_cash_handovers')->where('id', $id)->first();
        }, 3);
    }

    public function transfer(User $actor, string $id, string $action, array $input): void
    {
        abort_unless(in_array($action, ['dispatch', 'receive'], true), 422);
        $data = Validator::make($input, ['counted' => 'required|string|max:20', 'notes' => 'required|string|max:2000', 'cod_amount' => 'nullable|string|max:20'])->validate();
        $counted = $this->amount($data['counted']);
        DB::transaction(function () use ($actor, $id, $action, $data, $counted) {
            $actor = $this->actor($actor);
            $h = $this->scope(DB::table('logistics_cash_handovers'), $actor)->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($h->kind === 'transfer', 422);
            $ids = [(int) $h->hub_id, (int) $h->destination_hub_id]; sort($ids);
            foreach ($ids as $hub) $this->hub($hub, $actor->business_name);
            $target = $action === 'dispatch' ? (int) $h->hub_id : (int) $h->destination_hub_id;
            $this->staff($actor, $target);
            $movement = $action === 'dispatch' ? 'transfer_dispatched' : 'transfer_received';
            $existing = DB::table('logistics_cash_movements')->where('handover_id', $id)->where('kind', $movement)->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->actor_id === (int) $actor->id && abs((int) $existing->delta_cents) === $counted, 409);
                if (app(LogisticsTreasury::class)->installed()) {
                    $priorCod = $action === 'dispatch' ? $h->cod_released_cents : $h->cod_received_cents;
                    abort_unless((int)$priorCod === $this->amount((string)($data['cod_amount'] ?? '0')),409,'This transfer already has a different verified COD allocation.');
                }
                return;
            }
            abort_unless($h->status === ($action === 'dispatch' ? 'authorized' : 'in_transit'), 409, 'Cash transfer state changed.');
            if ($action === 'dispatch') {
                abort_unless($counted === (int) $h->expected_cents, 422, 'Release exactly the authorized transfer amount.');
                abort_unless($this->custody($target) >= $counted, 422, 'Insufficient verified hub cash custody.');
            }
            $cod = null;
            if (app(LogisticsTreasury::class)->installed()) {
                $cod = $this->amount((string) ($data['cod_amount'] ?? '0'));
                if ($action === 'dispatch') {
                    abort_unless($cod <= $counted && $cod <= app(LogisticsTreasury::class)->qualifiedHub($target), 422, 'The verified COD portion exceeds available qualified hub cash.');
                } else {
                    abort_unless($cod <= $counted && $cod <= (int) $h->cod_released_cents, 422, 'The verified COD receipt cannot exceed the released COD portion or actual cash received.');
                }
            }
            // Destination receipt records actual counted cash, including shortages and overages.
            $status = $action === 'dispatch' ? 'in_transit' : ($counted === (int) $h->expected_cents ? 'received' : 'discrepancy');
            $values = ['status' => $status, 'updated_at' => now()];
            if ($cod !== null) $values[$action === 'dispatch' ? 'cod_released_cents' : 'cod_received_cents'] = $cod;
            if ($action === 'dispatch') $values['dispatched_at'] = now();
            else $values += ['counted_cents' => $counted, 'counted_by' => $actor->id, 'counted_at' => now()];
            DB::table('logistics_cash_handovers')->where('id', $id)->update($values);
            $this->movement($actor, $h, $target, $movement, $action === 'dispatch' ? -$counted : $counted);
            $this->event($actor, $movement, $id, ['counted_cents' => $counted, 'cod_cents' => $cod, 'notes' => $data['notes'], 'status' => $status]);
        }, 3);
    }

    public function resolve(User $actor, string $id, string $notes): void
    {
        Validator::make(['notes' => $notes], ['notes' => 'required|string|min:3|max:2000'])->validate();
        DB::transaction(function () use ($actor, $id, $notes) {
            $actor = $this->actor($actor); app(LogisticsFinance::class)->admin($actor);
            $h = $this->scope(DB::table('logistics_cash_handovers'), $actor)->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($h->resolved_at) { abort_unless($h->resolution === $notes, 409); return; }
            abort_unless($h->status === 'discrepancy', 422);
            DB::table('logistics_cash_handovers')->where('id', $id)->update(['resolution' => $notes, 'resolved_at' => now(), 'resolved_by' => $actor->id, 'updated_at' => now()]);
            $this->event($actor, 'cash_discrepancy_resolved', $id, ['notes' => $notes, 'balances_changed' => false]);
        }, 3);
    }

    public function cutoff(User $actor, array $input): void
    {
        $data = Validator::make($input, ['cutoff' => ['required', 'date_format:H:i'], 'revision' => 'required|integer|min:1'])->validate();
        DB::transaction(function () use ($actor, $data) {
            $actor = $this->actor($actor); $finance = app(LogisticsFinance::class); $finance->admin($actor);
            $finance->settings($actor->business_name);
            $settings = DB::table('logistics_finance_settings')->where('company_name', $actor->business_name)->lockForUpdate()->first();
            abort_unless((int) $settings->revision === (int) $data['revision'], 409, 'Settings changed. Refresh before saving.');
            DB::table('logistics_finance_settings')->where('company_name', $actor->business_name)->update(['remittance_cutoff' => $data['cutoff'],
                'revision' => $settings->revision + 1, 'updated_by' => $actor->id, 'updated_at' => now()]);
            $this->event($actor, 'remittance_cutoff_updated', $actor->business_name, ['before' => $settings->remittance_cutoff, 'after' => $data['cutoff'], 'timezone' => 'Asia/Manila']);
        }, 3);
    }

    private function movement(User $actor, object $h, int $hub, string $kind, int $delta): void
    {
        DB::table('logistics_cash_movements')->insert(['handover_id' => $h->id, 'hub_id' => $hub, 'kind' => $kind,
            'delta_cents' => $delta, 'actor_id' => $actor->id, 'created_at' => now()]);
    }

    private function event(User $actor, string $event, string $id, array $details): void
    {
        app(LogisticsFinance::class)->event($actor->business_name, $event, 'cash_handover', $id, $details, $actor->id);
        $this->notifyHandover($actor, $event, $id);
    }

    /** Called only after a new financial event, in its existing transaction. */
    public function notifyHandover(User $actor, string $event, string $id): void
    {
        $messages = [
            'remittance_submitted'=>['Remittance awaiting count','A Rider remittance is awaiting physical count at your hub.'],
            'remittance_counted'=>['Remittance counted','Hub Staff recorded a physical remittance count. Review the verified amount and any difference in Cash Desk.'],
            'treasury_transfer_authorized'=>['Treasury release authorized','A treasury handover is authorized at your hub. Only confirm release after physically releasing the cash.'],
            'treasury_cash_released'=>['Treasury cash released','Hub Staff recorded treasury cash release. Verify actual company-account receipt in Seller Settlements.'],
            'treasury_receipt_verified'=>['Treasury receipt recorded','Logistics Admin recorded company treasury receipt. Review the received amount and any difference in Cash Desk.'],
        ];
        if (!isset($messages[$event])) return;
        $h=DB::table('logistics_cash_handovers')->where('id',$id)->where('company_name',$actor->business_name)->firstOrFail();
        $staff=User::where('business_name',$actor->business_name)->where('status','approved')->where('account_type','logistics')->where('is_logistics',true)->where('is_admin',false)->where('logistics_role','hub_staff')->where('logistics_hub_id',$h->hub_id)
            ->whereHas('logisticsHub',fn($q)=>$q->where('company_name',$actor->business_name)->where('is_operational',true))->pluck('id')->all();
        $admins=User::where('business_name',$actor->business_name)->where('status','approved')->where('account_type','logistics')->where('is_logistics',true)->where('is_admin',false)->where('logistics_role','admin')->pluck('id')->all();
        $recipients=match($event){
            'remittance_submitted','treasury_transfer_authorized','treasury_receipt_verified'=>$staff,
            'remittance_counted'=>array_merge($admins,User::whereKey($h->actor_id)->where('business_name',$actor->business_name)->where('status','approved')->where('account_type','rider')->where('is_admin',false)->where('is_logistics',false)->pluck('id')->all()),
            'treasury_cash_released'=>$admins,
        };
        foreach(array_unique($recipients) as $recipient){
            if((int)$recipient===(int)$actor->id)continue;
            \App\Models\Order::notifyUser((int)$recipient,$messages[$event][0],$messages[$event][1],'cash_handover',$id);
        }
    }
}
