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
        $data = $request->validate(['search' => 'nullable|string|max:100', 'role' => 'nullable|in:rider,hub_staff', 'status' => 'nullable|in:pending,interview,approved,rejected,suspended']);
        return redirect()->route(($data['role'] ?? null) === 'hub_staff' ? 'logistics.staff' : 'logistics.riders', array_intersect_key($data, array_flip(['search', 'status'])));
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
        $role = $account ? ($account->account_type === 'rider' ? 'rider' : 'hub_staff') : $request->input('role');
        $minimumAge = $role === 'rider' ? 18 : 16;
        $onboarding = !$account || in_array($account->status,['pending','interview'],true);
        $application=app(\App\Services\PersonnelApplication::class);$application->normalize($request);
        if (is_string($request->input('contact_no'))) {
            $request->merge(['contact_no' => \App\Support\PhilippineMobile::normalize($request->input('contact_no'))]);
        }
        $data = $request->validate([
            'role' => [$account ? 'sometimes' : 'required', Rule::in(['rider', 'hub_staff'])],
            'username' => ['required', 'alpha_dash', 'max:60', Rule::unique('users', 'username')->ignore($account?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account?->id)],
            'given_names' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}-]+(\s[\p{L}-]+)*$/u'],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}-]+(\s[\p{L}-]+)*$/u'],
            'middle_name' => ['nullable', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}-]+(\s[\p{L}-]+)*$/u'],
            'suffix'=>['nullable',new \Illuminate\Validation\Rules\Enum(\App\Enums\Suffix::class)], 'sex' => 'required|in:male,female', 'birthday' => 'required|date|before_or_equal:'.now()->subYears($minimumAge)->format('Y-m-d'),
            'contact_no' => ['bail', 'required', 'string', 'regex:/^09[0-9]{9}$/D', function ($attribute, $value, $fail) use ($account) {
                if (User::whereIn('contact_no', \App\Support\PhilippineMobile::variants($value))->when($account, fn ($query) => $query->where('id', '!=', $account->id))->exists()) {
                    $fail('This mobile number is already registered.');
                }
            }],
            'license_expiry' => $role === 'rider' ? ['required', 'date', 'after_or_equal:today'] : ['prohibited'],
            'barangay' => 'required|string|max:255',
            'password' => [$account ? 'nullable' : 'required', 'string', 'min:12', 'max:128', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'status' => $account ? 'prohibited' : 'sometimes|in:pending',
            'logistics_hub_id' => ['required', 'integer', Rule::exists('logistics_hubs', 'id')->where('company_name', auth()->user()->business_name)->where('is_operational', true)],
            'vehicle_type' => 'nullable|in:two_wheels,four_wheels', 'vehicle_ownership' => 'nullable|in:own,company',
        ], ['birthday.before_or_equal' => "You must be at least {$minimumAge} years old.",
            'license_expiry.after_or_equal' => 'The driver\'s license has expired. Enter today or a later expiry date.']);
        if($onboarding) {
            $extra=$request->validate(array_merge($application->rules($role,$request->input('vehicle_ownership'),$account),['username'=>['required','string','alpha_dash','min:8','max:30'],'email'=>['required','email','regex:/@gmail\.com$/i']]));
            $data=array_merge($data,$extra);
        } else {
            $request->validate(array_fill_keys(['id_file','selfie_file','license_file','or_file','cr_file','id_type_id','license_number','plate_number','vehicle_brand','vehicle_model'],'prohibited'));
        }
        $role = $account ? ($account->account_type === 'rider' ? 'rider' : 'hub_staff') : $data['role'];
        if ($role === 'rider') {
            $request->validate(['vehicle_type' => 'required|in:two_wheels,four_wheels', 'vehicle_ownership' => 'required|in:own,company']);
        }
        $hub = $this->companyHubs()->findOrFail($data['logistics_hub_id']);
        $data['barangay'] = app(HubAddress::class)->validateBarangay($hub, $data['barangay']);
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
        if($onboarding) {
            if(app(HubHiring::class)->identityQuery($data)->when($account,fn($q)=>$q->where('id','!=',$account->id))->lockForUpdate()->first()) throw \Illuminate\Validation\ValidationException::withMessages(['email'=>'An account with this identity already exists.']);
            if($role==='rider' && $data['vehicle_ownership']==='company' && !CompanyVehicle::typeAvailableAtHub($hub->id,$data['vehicle_type'])) throw \Illuminate\Validation\ValidationException::withMessages(['vehicle_type'=>'The selected company vehicle type is unavailable.']);
            $data['region']=$hub->region ?: $request->input('region');
            validator($data,['region'=>'required|string|max:255'])->validate();
            $data=array_merge($data,app(HubAddress::class)->validateAccount(array_intersect_key($data,array_flip(['region','province','municipality','barangay']))));
            $data=$application->store($request,$role,$data,$account);
        }
        if ($role !== 'rider') {
            $data['vehicle_type'] = null;
            $data['vehicle_ownership'] = null;
        }

        return $data;
    }

    public function storeAccount(Request $request)
    {
        $request->validate(['role'=>'required|in:rider,hub_staff']);
        $account = DB::transaction(function () use ($request) {
            app(\App\Services\PersonnelApplication::class)->normalize($request);
            app(HubHiring::class)->lockApplicationIdentity($request->all(),$request->input('role'));
            $hub=$this->companyHubs()->where('is_operational',true)->lockForUpdate()->findOrFail($request->input('logistics_hub_id'));
            $this->requireCurrentPersonnelAdmin();
            $data=$this->accountData($request);
            app(HubHiring::class)->assertIntake($hub,$data['account_type']==='rider'?'rider':'hub_staff',true);
            return User::create(array_merge($data,['auth_method'=>'manual']));
        },3);
        $request->attributes->set('logistics_record_id', $account->id);

        return redirect()->route('logistics.accounts.edit', $account->id)->with('success', 'Complete application created as Pending. Rider approval or the Hub Staff interview and approval are still required before login.');
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
        DB::transaction(function()use($request,$id){
            $peek=$this->managedUsers()->findOrFail($id);
            app(\App\Services\PersonnelApplication::class)->normalize($request);
            app(HubHiring::class)->lockApplicationIdentity($request->all(),$peek->account_type==='rider'?'rider':'hub_staff');
            \App\Models\LogisticsHub::whereIn('id',array_filter([$peek->logistics_hub_id,$request->input('logistics_hub_id')]))->orderBy('id')->lockForUpdate()->get();
            $this->requireCurrentPersonnelAdmin();
            $account=$this->managedUsers()->lockForUpdate()->findOrFail($id);
            $priorDocuments=array_intersect_key($account->getAttributes(),array_flip(\App\Services\SensitiveDocuments::FIELDS));
            $data=$this->accountData($request,$account);
            if(in_array($account->status,['pending','interview'],true)) {
                $hashes=[];foreach(\App\Services\SensitiveDocuments::FIELDS as $field) if(isset($data[$field]) && $data[$field]!==$account->{$field}) $hashes[$field]=['before'=>$account->{$field}?hash('sha256',$account->{$field}):null,'after'=>hash('sha256',$data[$field])];
                $account->hiringDecision=['action'=>'application_completed','document_changes'=>$hashes];
            }
            $account->update($data);
            foreach(array_unique(array_filter($priorDocuments)) as $oldPath) if(!in_array($oldPath,array_intersect_key($account->getAttributes(),array_flip(\App\Services\SensitiveDocuments::FIELDS)),true)) app(\App\Services\MediaLifecycle::class)->deleteAfterCommit($oldPath,'sensitive_documents');
        },3);

        return back()->with('success', 'Application details saved. Existing status and approval history were preserved.');
    }

    private function requireCurrentPersonnelAdmin(): void
    {
        $actor=User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
        abort_unless($actor->status==='approved' && $actor->account_type==='logistics' && $actor->is_logistics && $actor->isLogisticsAdmin() && !$actor->is_admin && $actor->business_name===auth()->user()->business_name,403);
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
        return response()->json(app(HubAddress::class)->locations($level, $code))
            ->header('X-Content-Type-Options', 'nosniff')->header('Cache-Control', 'private, no-store');
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
            'issueEvidence' => \Illuminate\Support\Facades\Schema::hasTable('logistics_finance_evidence')?DB::table('logistics_finance_evidence')->where('company_name',auth()->user()->business_name)->where('source_type','parcel_issue')->whereIn('source_id',$this->issueScope()->select('id'))->get()->groupBy('source_id'):collect(),
            'recoveryRiders' => \App\Models\User::where('business_name',auth()->user()->business_name)->where('account_type','rider')->where('status','suspended')->whereIn('id',$this->companyScope()->get()->flatMap(fn($s)=>[$s->pickup_rider_id,$s->courier_id,$s->hub_transfer_rider_id])->filter()->unique())->get(),
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
        return DB::transaction(function()use($request){
        $data = $request->validate(['tracking_number' => 'required|string|max:100', 'kind' => 'required|in:delivery_failed,lost,damaged,transfer_problem,other', 'description' => 'required|string|max:2000', 'hub_id' => [auth()->user()->isHubStaff() ? 'nullable' : 'required', 'integer', Rule::exists('logistics_hubs', 'id')->where('company_name', auth()->user()->business_name)->where('is_operational', true)]]);
        $shipment = $this->companyScope()->where('tracking_number', trim($data['tracking_number']))->firstOrFail();
        $hub = $this->companyHubs()->findOrFail(auth()->user()->isHubStaff() ? auth()->user()->logistics_hub_id : $data['hub_id']);
        $access = app(LogisticsAccess::class);
        $touchesHub = $access->matches($hub, $shipment->origin_hub, $shipment->origin_province) || $access->matches($hub, $shipment->destination_hub, $shipment->destination_province)
            || $shipment->hubLegs()->where(fn ($q) => $q->where(fn ($q) => $access->pair($q, 'from_hub', 'from_province', $hub))->orWhere(fn ($q) => $access->pair($q, 'to_hub', 'to_province', $hub)))->exists();
        abort_unless($touchesHub, 422, 'Choose a hub on this parcel route.');
        $issue = LogisticsIssue::create(['shipment_id' => $shipment->id, 'company_name' => auth()->user()->business_name, 'hub_id' => $hub->id,
            'reported_by' => auth()->id(), 'kind' => $data['kind'], 'description' => $data['description'], 'status' => 'open']);
        if($request->hasFile('proof'))app(\App\Services\LogisticsTreasury::class)->evidence(auth()->user(),'parcel_issue',(string)$issue->id,$request->file('proof'));
        $this->issueEvent($issue, $data['description']);
        $request->attributes->set('logistics_record_id', $issue->id);

        return back()->with('success', 'Issue recorded.');
        },3);
    }

    public function rts(Request $request, string $id, \App\Services\LogisticsRts $flow)
    {
        $flow->act($request->user(),$id,$request->all());
        return back()->with('success','Return custody recorded. Only Seller receipt restores inventory; payment review remains separate.');
    }

    public function updateIssue(Request $request, $id)
    {
        return DB::transaction(function()use($request,$id){
        $issue = $this->issueScope()->lockForUpdate()->findOrFail($id);
        abort_if($issue->rts,409,'Use authenticated RTS custody actions for this issue.');
        $data = $request->validate(['status'=>'required|in:open,in_progress,resolved','assigned_to'=>'nullable|integer','resolution'=>'nullable|string|max:2000','revision'=>'required|integer|min:0','outcome'=>'nullable|in:located,damage_documented,escalated,documented_only','custody_note'=>'nullable|string|max:1000','notify_participants'=>'nullable|boolean','public_update_confirmed'=>'sometimes|accepted']);
        abort_unless((int)($issue->investigation['revision']??0)===(int)$data['revision'],409,'Investigation changed. Refresh before saving.');
        $before=['status'=>$issue->status,'assigned_to'=>$issue->assigned_to,'outcome'=>$issue->investigation['outcome']??null];
        if($data['status']==='resolved')$request->validate(['outcome'=>'required|in:located,damage_documented,escalated,documented_only']);
        if($request->boolean('notify_participants')){
            abort_unless(auth()->user()->isLogisticsAdmin() && $data['status']==='resolved' && in_array($issue->kind,['lost','damaged']),403,'Only Admin-confirmed investigation milestones may notify participants.');
            $request->validate(['public_update_confirmed'=>'required|accepted']);
        }
        if ($data['status'] === 'resolved') {
            $request->validate(['resolution' => 'required|string|min:3|max:2000|regex:/[\p{L}\p{N}]/u']);
        }
        if (! empty($data['assigned_to'])) {
            $this->issueAssignees()->where('logistics_hub_id', $issue->hub_id)->findOrFail($data['assigned_to']);
        }
        $data['resolved_at'] = $data['status'] === 'resolved' ? now() : null;
        $investigation=['revision'=>(int)$data['revision']+1,'outcome'=>$data['status']==='resolved'?($data['outcome']??null):null,'custody_note'=>$data['custody_note']??null,'actor_id'=>auth()->id(),'recorded_at'=>now()->toIso8601String()];
        $issue->update(array_intersect_key($data,array_flip(['status','assigned_to','resolution','resolved_at']))+['investigation'=>$investigation]);
        if($request->hasFile('proof'))app(\App\Services\LogisticsTreasury::class)->evidence(auth()->user(),'parcel_issue',(string)$issue->id,$request->file('proof'));
        $context=['old'=>$before,'new'=>['status'=>$issue->status,'assigned_to'=>$issue->assigned_to,'outcome'=>$investigation['outcome']],'reason'=>$data['resolution']??null,'custody_note'=>$data['custody_note']??null,'revision'=>$investigation['revision']];
        $request->attributes->set('operational_changes',['issue:'.$issue->id=>$context]);
        $this->issueEvent($issue,json_encode($context));
        if($request->boolean('notify_participants')){
            foreach(array_unique(array_filter([$issue->shipment?->order?->buyer_id,$issue->shipment?->order?->seller_id])) as $recipient) \App\Models\Order::notifyUser($recipient,'Parcel investigation update','A confirmed investigation update was recorded. Contact support for next steps. Payment review remains separate.','order_status',$issue->shipment->order_id);
        }

        return back()->with('success', 'Issue updated.');
        },3);
    }
}
