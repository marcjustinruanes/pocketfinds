<?php

namespace App\Http\Controllers;

use App\Models\CompanyVehicle;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsHub;
use App\Models\LogisticsIssue;
use App\Models\Shipment;
use App\Models\User;
use App\Services\HubAddress;
use App\Services\HubHiring;
use App\Services\LogisticsAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LogisticsManagementController extends LogisticsController
{
    private function managedUsers()
    {
        return User::where('business_name', auth()->user()->business_name)->where(function ($q) {
            $q->where('account_type', 'rider')->orWhere(fn ($q) => $q->where('account_type', 'logistics')->where('is_logistics', true)->where('logistics_role', 'hub_staff'));
        });
    }

    private function companyHubs()
    {
        return LogisticsHub::where('company_name', auth()->user()->business_name)->orderBy('province')->orderBy('municipality');
    }

    public function accounts(Request $request)
    {
        $data = $request->validate(['search' => 'nullable|string|max:100', 'role' => 'nullable|in:rider,hub_staff', 'status' => 'nullable|in:pending,pending_activation,interview,approved,rejected,suspended']);
        $accounts = $this->managedUsers()->with('logisticsHub')
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('username', 'like', '%'.$s.'%')->orWhere('given_names', 'like', '%'.$s.'%')->orWhere('last_name', 'like', '%'.$s.'%')))
            ->when($data['role'] ?? null, fn ($q, $role) => $role === 'rider' ? $q->where('account_type', 'rider') : $q->where('logistics_role', 'hub_staff'))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest()->paginate(20)->withQueryString();

        return view('logistics.accounts', array_merge($this->sidebarCounts(), ['accounts' => $accounts, 'hubs' => $this->companyHubs()->where('is_operational', true)->get()]));
    }

    public function editAccount($id)
    {
        $account = $this->managedUsers()->findOrFail($id);
        $assignments = DeliveryAssignment::with('shipment')->where('courier_id', $account->id)
            ->whereHas('shipment', fn ($q) => $q->where('logistics_company', auth()->user()->business_name))->latest('accepted_at')->paginate(15);

        $history = DB::table('hiring_application_history')->where('user_id', $account->id)->where('company_name', auth()->user()->business_name)->orderByDesc('id')->paginate(20, ['*'], 'history_page')->withQueryString();

        return view('logistics.account-edit', array_merge($this->sidebarCounts(), ['account' => $account, 'hubs' => $this->companyHubs()->where('is_operational', true)->get(), 'assignments' => $assignments, 'applicationHistory' => $history]));
    }

    private function accountData(Request $request, ?User $account = null): array
    {
        $data = $request->validate([
            'role' => [$account ? 'sometimes' : 'required', Rule::in(['rider', 'hub_staff'])],
            'username' => ['required', 'alpha_dash', 'max:60', Rule::unique('users', 'username')->ignore($account?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account?->id)],
            'given_names' => 'required|string|max:255', 'last_name' => 'required|string|max:255',
            'sex' => 'required|in:male,female', 'birthday' => 'required|date|before_or_equal:'.now()->subYears(16)->format('Y-m-d'),
            'contact_no' => 'required|digits:11', 'barangay' => 'required|string|max:255',
            'password' => [$account ? 'nullable' : 'required', 'string', 'min:12', 'max:128', 'confirmed'],
            'status' => $account ? 'prohibited' : 'sometimes|in:pending',
            'logistics_hub_id' => ['required', 'integer', Rule::exists('logistics_hubs', 'id')->where('company_name', auth()->user()->business_name)->where('is_operational', true)],
            'vehicle_type' => 'nullable|in:two_wheels,four_wheels', 'vehicle_ownership' => 'nullable|in:own,company',
        ]);
        $role = $account ? ($account->account_type === 'rider' ? 'rider' : 'hub_staff') : $data['role'];
        if ($role === 'rider') {
            $request->validate(['vehicle_type' => 'required|in:two_wheels,four_wheels', 'vehicle_ownership' => 'required|in:own,company']);
        }
        $hub = $this->companyHubs()->findOrFail($data['logistics_hub_id']);
        if ($account && (int) $account->logistics_hub_id !== (int) $hub->id) {
            abort_if(Shipment::where('logistics_company', $account->business_name)->whereNotIn('shipping_status', ['delivered', 'completed', 'returned', 'cancelled'])
                ->where(fn ($q) => $q->where('courier_id', $account->id)->orWhere('pickup_rider_id', $account->id)->orWhere('hub_transfer_rider_id', $account->id))->exists(), 422, 'Finish or reassign active parcels before moving this rider to another hub.');
        }
        unset($data['role'], $data['status']);
        if (! $account) {
            $data['status'] = 'pending';
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data = array_merge($data, [
            'account_type' => $role === 'rider' ? 'rider' : 'logistics', 'is_logistics' => $role === 'hub_staff', 'is_admin' => false,
            'logistics_role' => $role === 'hub_staff' ? 'hub_staff' : null, 'business_name' => $hub->company_name,
            'province' => $hub->province, 'municipality' => $hub->municipality, 'age' => Carbon::parse($data['birthday'])->age,
        ]);
        if ($role !== 'rider') {
            $data['vehicle_type'] = null;
            $data['vehicle_ownership'] = null;
        }

        return $data;
    }

    public function storeAccount(Request $request)
    {
        if ($request->input('role') === 'rider') {
            return app(RiderInvitationController::class)->store($request, app(\App\Services\RiderInvitations::class));
        }
        $data = $this->accountData($request);
        $account = DB::transaction(function () use ($data) {
            $hub = $this->companyHubs()->where('is_operational', true)->lockForUpdate()->findOrFail($data['logistics_hub_id']);
            app(HubHiring::class)->assertIntake($hub, $data['account_type'] === 'rider' ? 'rider' : 'hub_staff', true);

            return User::create(array_merge($data, ['auth_method' => 'manual']));
        });
        $request->attributes->set('logistics_record_id', $account->id);

        return redirect()->route('logistics.accounts.edit', $account->id)->with('success', 'Account created. Share the credentials privately with the new staff member.');
    }

    public function storeRiderAccount(Request $request)
    {
        $request->validate(['role' => ['required', Rule::in(['rider'])]]);

        return $this->storeAccount($request);
    }

    public function storeStaffAccount(Request $request)
    {
        $request->validate(['role' => ['required', Rule::in(['hub_staff'])]]);

        return $this->storeAccount($request);
    }

    public function updateAccount(Request $request, $id)
    {
        $account = $this->managedUsers()->findOrFail($id);
        $account->update($this->accountData($request, $account));

        return back()->with('success', 'Account updated.');
    }

    public function activity(Request $request)
    {
        $data = $request->validate(['search' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date'.($request->filled('from') ? '|after_or_equal:from' : '')]);
        $entries = DB::table('logistics_activity_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.actor_id')
            ->where('l.company_name', auth()->user()->business_name)
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('l.action', 'like', '%'.$s.'%')->orWhere('l.record_id', 'like', '%'.$s.'%')->orWhere('u.username', 'like', '%'.$s.'%')))
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('l.created_at', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('l.created_at', '<=', $v))
            ->select('l.*', 'u.username')->orderByDesc('l.id')->paginate(30)->withQueryString();

        return view('logistics.activity', array_merge($this->sidebarCounts(), compact('entries')));
    }

    public function storeHub(Request $request)
    {
        $data = app(HubAddress::class)->validate($this->hubInput($request));
        if ($this->companyHubs()->whereRaw('LOWER(province) = ?', [mb_strtolower(trim($data['province']))])->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($data['municipality']))])->exists()) {
            return back()->withErrors(['municipality' => 'This company already has a hub in that municipality and province.'])->withInput();
        }
        $data['province'] = trim($data['province']);
        $data['municipality'] = trim($data['municipality']);
        $hub = LogisticsHub::create(array_merge($data, ['company_name' => auth()->user()->business_name, 'is_operational' => true]));
        $request->attributes->set('logistics_record_id', $hub->id);

        return back()->with('success', 'Municipal hub created.');
    }

    public function updateHub(Request $request, $id)
    {
        $hub = $this->companyHubs()->findOrFail($id);
        $input = $this->hubInput($request, true);
        $data = app(HubAddress::class)->validate($input, $hub);
        unset($data['province'], $data['municipality']);
        $data['is_operational'] = $input['is_operational'];
        if (! $data['is_operational']) {
            $inUse = $this->companyScope()->whereNotIn('shipping_status', ['delivered', 'completed', 'returned', 'cancelled'])
                ->where(function ($q) use ($hub) {
                    $access = app(LogisticsAccess::class);
                    $q->where(fn ($q) => $access->pair($q, 'origin_hub', 'origin_province', $hub))
                        ->orWhere(fn ($q) => $access->pair($q, 'destination_hub', 'destination_province', $hub))
                        ->orWhereHas('hubLegs', fn ($q) => $q->where(fn ($q) => $access->pair($q, 'from_hub', 'from_province', $hub))->orWhere(fn ($q) => $access->pair($q, 'to_hub', 'to_province', $hub)));
                })->exists();
            abort_if($inUse, 422, 'This hub has active parcels. Complete their route before closing the hub.');
        }
        $hub->update($data);

        return back()->with('success', 'Hub details updated.');
    }

    private function hubAddressRules(): array
    {
        return ['province_code' => 'required|regex:/^[0-9]{9}$/', 'municipality_code' => 'required|regex:/^[0-9]{9}$/',
            'barangay_code' => 'required|regex:/^[0-9]{9}$/', 'name' => 'required|string|max:255',
            'address_line' => 'required|string|max:200', 'contact_no' => 'required|string|regex:/^09[0-9]{9}$/'];
    }

    private function hubInput(Request $request, bool $editing = false): array
    {
        return $request->validate(array_merge($this->hubAddressRules(), $editing ? ['is_operational' => 'required|boolean'] : []),
            ['contact_no.regex' => 'Contact number must contain exactly 11 digits and start with 09.'],
            ['province_code' => 'province', 'municipality_code' => 'municipality / city', 'barangay_code' => 'barangay',
                'address_line' => 'street / building address', 'contact_no' => 'contact number', 'name' => 'hub name']);
    }

    public function hubLocations(string $level, ?string $code = null)
    {
        return response()->json(app(HubAddress::class)->locations($level, $code));
    }

    public function updateVehicle(Request $request, $id)
    {
        $vehicle = CompanyVehicle::where('company_name', auth()->user()->business_name)->findOrFail($id);
        $data = $request->validate(['is_available' => 'required|boolean', 'status_reason' => 'nullable|string|max:500']);
        $vehicle->update($data);

        return back()->with('success', 'Vehicle availability and maintenance note updated.');
    }

    private function issueScope()
    {
        $query = LogisticsIssue::where('company_name', auth()->user()->business_name)
            ->whereIn('shipment_id', $this->companyScope()->select('shipments.id'));
        if (auth()->user()->isHubStaff()) {
            $query->where('hub_id', auth()->user()->logistics_hub_id);
        }

        return $query;
    }

    private function issueAssignees()
    {
        return $this->managedUsers()->where('status', 'approved')
            ->when(auth()->user()->isHubStaff(), fn ($q) => $q->where('logistics_hub_id', auth()->user()->logistics_hub_id));
    }

    public function issues()
    {
        return view('logistics.issues', array_merge($this->sidebarCounts(), [
            'shipments' => $this->companyScope()->with(['order.buyer', 'courier'])->whereIn('shipping_status', ['delivery_failed', 'returned', 'cancelled'])->latest()->paginate(15),
            'issues' => $this->issueScope()->with(['shipment', 'assignee'])->latest()->paginate(15, ['*'], 'issue_page')->withQueryString(),
            'assignees' => $this->issueAssignees()->get(),
            'issueHubs' => $this->companyHubs()->where('is_operational', true)->get(),
            'issueEvents' => DB::table('logistics_issue_events')->whereIn('issue_id', $this->issueScope()->select('logistics_issues.id'))->latest('id')->limit(30)->get(),
        ]));
    }

    private function issueEvent(LogisticsIssue $issue, ?string $note): void
    {
        DB::table('logistics_issue_events')->insert(['issue_id' => $issue->id, 'actor_id' => auth()->id(), 'status' => $issue->status, 'assigned_to' => $issue->assigned_to, 'note' => $note, 'created_at' => now()]);
    }

    public function storeIssue(Request $request)
    {
        $data = $request->validate(['tracking_number' => 'required|string|max:100', 'kind' => 'required|in:delivery_failed,damaged,transfer_problem,other', 'description' => 'required|string|max:2000', 'hub_id' => [auth()->user()->isHubStaff() ? 'nullable' : 'required', 'integer', Rule::exists('logistics_hubs', 'id')->where('company_name', auth()->user()->business_name)->where('is_operational', true)]]);
        $shipment = $this->companyScope()->where('tracking_number', trim($data['tracking_number']))->firstOrFail();
        $hub = $this->companyHubs()->findOrFail(auth()->user()->isHubStaff() ? auth()->user()->logistics_hub_id : $data['hub_id']);
        $access = app(LogisticsAccess::class);
        $touchesHub = $access->matches($hub, $shipment->origin_hub, $shipment->origin_province) || $access->matches($hub, $shipment->destination_hub, $shipment->destination_province)
            || $shipment->hubLegs()->where(fn ($q) => $q->where(fn ($q) => $access->pair($q, 'from_hub', 'from_province', $hub))->orWhere(fn ($q) => $access->pair($q, 'to_hub', 'to_province', $hub)))->exists();
        abort_unless($touchesHub, 422, 'Choose a hub on this parcel route.');
        $issue = LogisticsIssue::create(['shipment_id' => $shipment->id, 'company_name' => auth()->user()->business_name, 'hub_id' => $hub->id,
            'reported_by' => auth()->id(), 'kind' => $data['kind'], 'description' => $data['description'], 'status' => 'open']);
        $this->issueEvent($issue, $data['description']);
        $request->attributes->set('logistics_record_id', $issue->id);

        return back()->with('success', 'Issue recorded.');
    }

    public function updateIssue(Request $request, $id)
    {
        $issue = $this->issueScope()->lockForUpdate()->findOrFail($id);
        $data = $request->validate(['status' => 'required|in:open,in_progress,resolved', 'assigned_to' => 'nullable|integer', 'resolution' => 'nullable|string|max:2000']);
        if ($data['status'] === 'resolved') {
            $request->validate(['resolution' => 'required|string|max:2000']);
        }
        if (! empty($data['assigned_to'])) {
            $this->issueAssignees()->where('logistics_hub_id', $issue->hub_id)->findOrFail($data['assigned_to']);
        }
        $data['resolved_at'] = $data['status'] === 'resolved' ? now() : null;
        $issue->update($data);
        $this->issueEvent($issue, $data['resolution'] ?? null);

        return back()->with('success', 'Issue updated.');
    }
}
