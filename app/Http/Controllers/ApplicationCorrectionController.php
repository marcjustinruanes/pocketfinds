<?php

namespace App\Http\Controllers;

use App\Enums\{Sex, Suffix};
use App\Models\{LogisticsHub, User};
use App\Services\{CompanyOwnership, HubAddress, HubHiring, MediaLifecycle, PersonnelApplication, RiderActivation};
use App\Support\{ApplicationCorrection, LogisticsDocuments, PhilippineMobile};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApplicationCorrectionController extends Controller
{
    private function applicant(Request $request): User
    {
        $grant = $request->session()->get('application_correction');
        abort_unless(is_array($grant) && isset($grant['id'], $grant['fingerprint']) && ($grant['expires_at'] ?? 0) > now()->timestamp, 403, 'Sign in again to correct your application.');
        abort_if($request->user(), 403);
        $user = User::whereKey($grant['id'])->where('status', 'rejected')->where('is_admin', false)->whereIn('account_type', ['buyer','rider','logistics'])->firstOrFail();
        abort_unless($user->can_resubmit,403,'Editing and resubmission are not permitted for this application.');
        abort_unless(hash_equals(ApplicationCorrection::fingerprint($user), $grant['fingerprint']), 403, 'Your application has changed. Sign in again.');
        return $user;
    }

    private function documents(User $user, mixed $ownership = null): array
    {
        $definitions = ['id_file'=>['Government-issued ID','id_files'], 'selfie_file'=>['Selfie','selfie_files']];
        if ($user->account_type === 'rider') {
            $definitions = ['license_file'=>["Driver's license",'license_files'], 'selfie_file'=>['Selfie with license','selfie_files'], 'nbi_clearance_file'=>['NBI clearance','nbi_clearances']];
            if (($ownership ?? $user->vehicle_ownership) === 'own') $definitions += ['or_file'=>['Official receipt (OR)','vehicle_docs'], 'cr_file'=>['Certificate of registration (CR)','vehicle_docs']];
        }
        if (CompanyOwnership::founder($user)) {
            $definitions['business_permit_file'] = ['Business permit','business_permits'];
            foreach (LogisticsDocuments::LABELS as $field=>$label) $definitions[$field] = [$label,'business_certificates'];
        }
        $documents = [];
        foreach ($definitions as $field=>[$label,$directory]) {
            $path = $user->{$field};
            $exists = app(PersonnelApplication::class)->readable($path);
            // Older non-personnel applications may still have genuine public documents.
            if (!$exists && app(HubHiring::class)->role($user) === null && $path && \App\Services\SensitiveDocuments::safe($path)) $exists = Storage::disk('public')->exists($path);
            $documents[$field] = compact('label','directory','exists');
        }
        return $documents;
    }

    public function edit(Request $request)
    {
        $applicant = $this->applicant($request);
        return response()->view('auth.application-correction', [
            'applicant'=>$applicant, 'documents'=>$this->documents($applicant, $applicant->account_type === 'rider' ? 'own' : null),
            'idTypes'=>DB::table('id_types')->orderBy('name')->get(),
            'hub'=> $applicant->logistics_hub_id ? LogisticsHub::find($applicant->logistics_hub_id) : null,
            'isFounder'=>CompanyOwnership::founder($applicant), 'personnelRole'=>app(HubHiring::class)->role($applicant),
        ])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
    }

    public function update(Request $request)
    {
        $applicant = $this->applicant($request);
        $founder = CompanyOwnership::founder($applicant);
        $role = app(HubHiring::class)->role($applicant);
        app(PersonnelApplication::class)->normalize($request);
        if (is_string($request->input('tin'))) $request->merge(['tin'=>preg_replace('/[-\s]/u','',$request->input('tin'))]);
        $name = ['required','string','min:2','max:50',"regex:/^[\\p{L}'’\\-]+(\\s[\\p{L}'’\\-]+)*$/u"];
        $rules = [
            'given_names'=>$name, 'last_name'=>$name, 'middle_name'=>array_replace($name,[0=>'nullable']),
            'suffix'=>['nullable',Rule::enum(Suffix::class)], 'sex'=>['required',Rule::enum(Sex::class)],
            'birthday'=>'required|date|before_or_equal:'.now()->subYears(in_array($applicant->account_type,['buyer','rider'])?18:16)->toDateString(),
            'contact_no'=>['required','string','regex:/^09\d{9}$/D',function ($attribute,$value,$fail) use ($applicant) {
                if (User::whereKeyNot($applicant->id)->whereIn('contact_no',PhilippineMobile::variants($value))->exists()) $fail('This mobile number is already registered.');
            }],
            'region'=>'required|string|max:255', 'province'=>'required|string|max:255', 'municipality'=>'required|string|max:255', 'barangay'=>'required|string|max:255',
            'house_no'=>'nullable|string|max:50', 'street'=>'nullable|string|max:100',
            'id_type_id'=>$role==='rider' ? 'prohibited' : 'required|integer|exists:id_types,id',
            'email'=>'prohibited', 'username'=>'prohibited', 'password'=>'prohibited', 'status'=>'prohibited',
            'is_admin'=>'prohibited', 'is_logistics'=>'prohibited', 'account_type'=>'prohibited', 'logistics_role'=>'prohibited', 'logistics_hub_id'=>'prohibited', 'business_name'=>'prohibited',
        ];
        if ($founder) $rules += [
            'position'=>'required|string|max:100', 'postal_code'=>'required|regex:/^\d{4}$/D',
            'representative_email'=>'nullable|email|max:255',
            'representative_contact_no'=>['nullable','regex:/^09\d{9}$/'],
            'tin'=>['required','string','regex:/^\d{12}$/D',Rule::unique('users','tin')->ignore($applicant->id)],
            'sec_registration_no'=>['required','string','regex:/^[A-Z]{2}\d{9}$/D',Rule::unique('users','sec_registration_no')->ignore($applicant->id)],
        ];
        if (is_string($request->input('sec_registration_no'))) $request->merge(['sec_registration_no'=>strtoupper(trim($request->input('sec_registration_no')))]);
        if ($role) $rules = array_replace($rules, app(PersonnelApplication::class)->rules($role,$request->input('vehicle_ownership'),$applicant));
        if ($role) $rules['resume_file'] = 'nullable|file|mimes:pdf|max:5120';
        if ($founder) $rules['company_logo'] = 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120';
        $documents = $this->documents($applicant, $request->input('vehicle_ownership'));
        foreach ($documents as $field=>$document) $rules[$field] = [$document['exists']?'nullable':'required','file',$field==='selfie_file'?'mimes:jpg,jpeg,png':'mimes:jpg,jpeg,png,pdf','max:5120'];
        $data = $request->validate($rules);
        foreach (array_keys($documents) as $field) unset($data[$field]);
        unset($data['resume_file'], $data['company_logo']);
        // Uploads are stored separately; submitted document paths are never mass assigned.
        foreach (['id_file','selfie_file','license_file','nbi_clearance_file','or_file','cr_file'] as $field) unset($data[$field]);
        $data = array_replace($data, app(HubAddress::class)->validateAccount($data));
        $data['age'] = Carbon::parse($data['birthday'])->age;

        DB::transaction(function () use ($applicant,$request,$data,$documents,$role) {
            $hiring = app(HubHiring::class);
            $identity = array_replace($applicant->getAttributes(),$data);
            $hiring->lockApplicationIdentity($identity,$role);
            $hub = $role ? LogisticsHub::whereKey($applicant->logistics_hub_id)->lockForUpdate()->firstOrFail() : null;
            $current = User::whereKey($applicant->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status==='rejected' && !$current->is_admin && hash_equals(ApplicationCorrection::fingerprint($applicant),ApplicationCorrection::fingerprint($current)),409,'This application has already changed. Sign in again.');
            abort_unless($current->can_resubmit,403,'Resubmission permission has been withdrawn.');
            if ($role) {
                $companyApproved = User::where('account_type','logistics')->where('is_logistics',true)->where('business_name',$current->business_name)->where('status','approved')->where(fn($q)=>$q->where('logistics_role','admin')->orWhereNull('logistics_role'))->exists();
                abort_unless($hub->company_name===$current->business_name && $companyApproved,422,'Your company must be approved before resubmission.');
                $hiring->assertIntake($hub,$role,true);
                if (strcasecmp($data['province'],$hub->province)!==0 || strcasecmp($data['municipality'],$hub->municipality)!==0) throw \Illuminate\Validation\ValidationException::withMessages(['municipality'=>'Your address must belong to your assigned hub municipality.']);
                if ($role==='rider' && $data['vehicle_ownership']==='company' && !\App\Models\CompanyVehicle::typeAvailableAtHub($hub->id,$data['vehicle_type'])) throw \Illuminate\Validation\ValidationException::withMessages(['vehicle_type'=>'The selected company vehicle type is unavailable.']);
            }
            if (User::whereKeyNot($current->id)->whereIn('contact_no',PhilippineMobile::variants($data['contact_no']))->lockForUpdate()->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['contact_no'=>'This mobile number is already registered.']);
            if ($hiring->identityQuery($identity)->whereKeyNot($current->id)->lockForUpdate()->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['given_names'=>'An account with this identity is already registered.']);
            if (Schema::hasTable('hiring_application_history')) DB::table('hiring_application_history')->insert([
                'user_id'=>$current->id,'attempt_id'=>(string)Str::uuid(),'company_name'=>$current->business_name ?? '', 'hub_id'=>$current->logistics_hub_id,
                'role'=>$role ?? $current->account_type,'status'=>'rejected','reason'=>$current->status_reason,'occurred_at'=>$current->updated_at ?? now(),'recorded_at'=>now(),'legacy_snapshot'=>true,
            ]);
            $current->fill($data);
            foreach ($documents as $field=>$document) if ($request->hasFile($field)) {
                $previous = $current->{$field};
                $current->{$field} = app(MediaLifecycle::class)->store($request->file($field),$document['directory'],'sensitive_documents');
                if ($previous) app(MediaLifecycle::class)->deleteAfterCommit($previous,Storage::disk('sensitive_documents')->exists($previous)?'sensitive_documents':'public');
            }
            $optionalFiles = $role ? ['resume_file'=>['resume_files','sensitive_documents']] : (CompanyOwnership::founder($current) ? ['company_logo'=>['logistics_logos','public']] : []);
            foreach ($optionalFiles as $field=>[$directory,$disk]) if ($request->hasFile($field)) {
                $previous=$current->{$field};
                $current->{$field}=app(MediaLifecycle::class)->store($request->file($field),$directory,$disk);
                if ($previous) app(MediaLifecycle::class)->deleteAfterCommit($previous,$disk);
            }
            if ($role==='rider') {
                $current->id_file = $current->license_file;
                $current->id_type_id = DB::table('id_types')->where('name',"Driver's License")->value('id');
                if (!$current->id_type_id) throw \Illuminate\Validation\ValidationException::withMessages(['license_file'=>"The Driver's License identity type must be configured before resubmission."]);
            }
            if ($role) $current->hiringDecision = ['action'=>'application_correction'];
            $current->status='pending'; $current->status_reason=null;
            $current->can_resubmit=false;
            $current->interview_scheduled_at=null; $current->interview_location=null;
            $current->save();
            if (!$role && Schema::hasTable('hiring_application_history')) DB::table('hiring_application_history')->insert([
                'user_id'=>$current->id,'attempt_id'=>(string)Str::uuid(),'company_name'=>$current->business_name ?? '',
                'role'=>$current->account_type,'status'=>'pending','occurred_at'=>now(),'recorded_at'=>now(),'legacy_snapshot'=>false,
            ]);
            if ($role==='rider') {
                $invitation = app(RiderActivation::class)->invitation($current);
                if ($invitation && !$invitation->activated_at) app(RiderActivation::class)->issue($current,User::findOrFail($invitation->invited_by));
            }
        },3);
        $request->session()->forget('application_correction');
        $message = $role==='rider' && app(RiderActivation::class)->pending($applicant->fresh())
            ? 'Your corrected application has been resubmitted. Check your email to set your rider password.'
            : 'Your corrected application has been resubmitted and is awaiting '.($role?'company':'admin').' review.';
        return redirect()->route('login')->with('success',$message);
    }
}
