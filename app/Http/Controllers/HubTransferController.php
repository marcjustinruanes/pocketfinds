<?php

namespace App\Http\Controllers;

use App\Models\CompanyVehicle;
use App\Models\HubTransferBatch;
use App\Models\LogisticsHub;
use App\Models\ShipmentHubLeg;
use App\Models\User;
use App\Services\HubTransferWorkflow;
use Illuminate\Http\Request;

class HubTransferController extends LogisticsController
{
    public function index(Request $request, HubTransferWorkflow $flow)
    {
        $data = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:'.implode(',', HubTransferBatch::STATUSES), 'direction' => 'nullable|in:incoming,outgoing', 'hub_id' => 'nullable|integer', 'from' => 'nullable|date', 'to' => 'nullable|date'.($request->filled('from') ? '|after_or_equal:from' : '')]);
        $user = $request->user();
        $query = $flow->scope($user)->with(['requester', 'approver', 'rider', 'dispatcher', 'receiver'])->withCount('members');
        if ($request->filled('hub_id')) {
            $hub = LogisticsHub::where('company_name', $user->business_name)->findOrFail($request->integer('hub_id'));
            if ($user->isHubStaff()) {
                abort_unless((int) $hub->id === (int) $user->logistics_hub_id, 404);
            }
            $query->where(fn ($q) => $q->where('origin_hub_id', $hub->id)->orWhere('destination_hub_id', $hub->id));
        }
        if ($user->isHubStaff() && ($data['direction'] ?? null)) {
            $query->where($data['direction'] === 'incoming' ? 'destination_hub_id' : 'origin_hub_id', $user->logistics_hub_id);
        }
        $query->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('requested_at', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('requested_at', '<=', $v))
            ->when($data['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('id', 'like', '%'.$v.'%')->orWhere('from_hub', 'like', '%'.$v.'%')->orWhere('to_hub', 'like', '%'.$v.'%')->orWhereHas('members', fn ($q) => $q->where('tracking_number', 'like', '%'.$v.'%'))));
        $batches = $query->latest('created_at')->orderByDesc('id')->paginate(20)->withQueryString();
        $hubs = LogisticsHub::where('company_name', $user->business_name)->where('is_operational', true)->orderBy('province')->orderBy('municipality')->get();

        return view('logistics.transfers', array_merge($this->sidebarCounts(), compact('batches', 'hubs')));
    }

    public function show(Request $request, string $id, HubTransferWorkflow $flow)
    {
        $batch = $flow->scope($request->user())->with(['requester', 'approver', 'dispatcher', 'receiver', 'rider', 'vehicle', 'origin', 'destination'])->findOrFail($id);
        $members = $batch->members()->with(['leg', 'receiver'])->orderBy('id')->paginate(30)->withQueryString();
        $events = $batch->events()->with('actor')->reorder()->orderByDesc('id')->paginate(30, ['*'], 'event_page')->withQueryString();
        $riders = $request->user()->isLogisticsAdmin() && $batch->status === 'approved'
            ? User::where('account_type', 'rider')->where('business_name', $batch->company_name)->where('status', 'approved')->where('logistics_hub_id', $batch->origin_hub_id)->where('vehicle_type', 'four_wheels')->orderBy('username')->get() : collect();
        $vehicles = $request->user()->isLogisticsAdmin() && $batch->status === 'approved'
            ? CompanyVehicle::where('company_name', $batch->company_name)->where('logistics_hub_id', $batch->origin_hub_id)->where('vehicle_type', 'four_wheels')->riderVisible()->get() : collect();

        return view('logistics.transfer-show', array_merge($this->sidebarCounts(), compact('batch', 'members', 'events', 'riders', 'vehicles')));
    }

    public function create(Request $request)
    {
        $request->validate(['origin_hub_id' => 'nullable|integer', 'destination_hub_id' => 'nullable|integer']);
        $user = $request->user();
        $hubs = LogisticsHub::where('company_name', $user->business_name)->where('is_operational', true)->orderBy('province')->orderBy('municipality')->get();
        $originId = $user->isHubStaff() ? $user->logistics_hub_id : $request->integer('origin_hub_id');
        if ($user->isHubStaff() && $request->filled('origin_hub_id')) {
            abort_unless($request->integer('origin_hub_id') === (int) $user->logistics_hub_id, 403);
        }
        $origin = $originId ? $hubs->firstWhere('id', $originId) : null;
        $destination = $request->filled('destination_hub_id') ? $hubs->firstWhere('id', $request->integer('destination_hub_id')) : null;
        abort_if(($originId && ! $origin) || ($request->filled('destination_hub_id') && ! $destination), 404);
        $candidates = ShipmentHubLeg::forCompany($user->business_name)->with('shipment')->where('status', 'pending')->whereNull('transfer_batch_id')->whereNull('requested_at')->whereNull('approved_at');
        if ($origin && $destination) {
            abort_if($origin->id === $destination->id, 422, 'Choose a different destination.');
            $candidates->fromHub($origin->municipality, $origin->province)->whereRaw('LOWER(to_hub) = ?', [mb_strtolower(trim($destination->municipality))])->whereRaw('LOWER(to_province) = ?', [mb_strtolower(trim($destination->province))])
                ->whereHas('shipment', fn ($q) => $q->whereIn('shipping_status', ['at_sorting_center', 'hub_transfer']))
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('shipment_hub_legs as earlier')->whereColumn('earlier.shipment_id', 'shipment_hub_legs.shipment_id')
                    ->where(fn ($q) => $q->where('earlier.status', 'in_transit')->orWhere(fn ($q) => $q->whereColumn('earlier.sequence', '<', 'shipment_hub_legs.sequence')->where('earlier.status', '!=', 'completed'))));
        } else {
            $candidates->whereRaw('1 = 0');
        }
        $candidates = $candidates->orderBy('shipment_id')->paginate(50)->withQueryString();

        return view('logistics.transfer-create', array_merge($this->sidebarCounts(), compact('hubs', 'origin', 'destination', 'candidates')));
    }

    private function result(Request $request, HubTransferBatch $batch)
    {
        $request->attributes->set('logistics_record_ids', $batch->members()->whereNotNull('leg_id')->pluck('leg_id')->all());
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'batch_id' => $batch->id, 'status' => $batch->status]);
        }

        return redirect()->route('logistics.transfers.show', $batch->id)->with('success', 'Transfer updated.');
    }

    public function store(Request $request, HubTransferWorkflow $flow)
    {
        $data = $request->validate(['origin_hub_id' => 'required|integer', 'destination_hub_id' => 'required|integer|different:origin_hub_id', 'parcel_ids' => 'required|array|min:1|max:2000', 'parcel_ids.*' => 'required|string|max:36|distinct']);

        return $this->result($request, $flow->request($request->user(), $data['origin_hub_id'], $data['destination_hub_id'], $data['parcel_ids']));
    }

    public function approve(Request $r, string $id, HubTransferWorkflow $flow)
    {
        return $this->result($r, $flow->decide($r->user(), $id, 'approved'));
    }

    public function reject(Request $r, string $id, HubTransferWorkflow $flow)
    {
        $data = $r->validate(['reason' => 'required|string|max:1000']);

        return $this->result($r, $flow->decide($r->user(), $id, 'rejected', $data['reason']));
    }

    public function cancel(Request $r, string $id, HubTransferWorkflow $flow)
    {
        $data = $r->validate(['reason' => 'required|string|max:1000']);

        return $this->result($r, $flow->decide($r->user(), $id, 'cancelled', $data['reason']));
    }

    public function dispatch(Request $r, string $id, HubTransferWorkflow $flow)
    {
        $data = $r->validate(['courier_id' => 'required|integer', 'vehicle_id' => 'nullable|integer']);

        return $this->result($r, $flow->dispatch($r->user(), $id, $data['courier_id'], $data['vehicle_id'] ?? null));
    }

    public function receive(Request $r, string $id, HubTransferWorkflow $flow)
    {
        $data = $r->validate(['tracking_number' => 'nullable|string|max:255', 'confirm_all' => 'nullable|required_without:tracking_number|boolean']);
        abort_unless(filled($data['tracking_number'] ?? null) || $r->boolean('confirm_all'), 422, 'Confirm all remaining parcels or scan a tracking number.');
        $ids = null;
        if (filled($data['tracking_number'] ?? null)) {
            $batch = $flow->scope($r->user())->findOrFail($id);
            $ids = $batch->members()->where('tracking_number', trim($data['tracking_number']))->pluck('shipment_id')->all();
            abort_unless(count($ids) === 1, 422, 'Tracking number must identify one parcel in this batch.');
        }

        return $this->result($r, $flow->receive($r->user(), $id, $ids));
    }

    public function discrepancy(Request $r, string $id, HubTransferWorkflow $flow)
    {
        $data = $r->validate(['reason' => 'required|string|max:1000', 'shipment_id' => 'nullable|string|max:36']);

        return $this->result($r, $flow->discrepancy($r->user(), $id, $data['reason'], $data['shipment_id'] ?? null));
    }
}
