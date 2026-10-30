<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/** Shared application requirements; registration and managed forms keep their existing entry points. */
class PersonnelApplication
{
    public function normalize(Request $request): void
    {
        $request->validate(array_fill_keys(['email','username','given_names','last_name','middle_name','suffix','birthday','sex','contact_no','license_number','plate_number','vehicle_ownership','vehicle_type'],'sometimes|nullable|string'));
        $values=[];
        foreach(['license_number','plate_number'] as $field) if(is_string($request->input($field))) $values[$field]=trim($request->input($field));
        if(is_string($request->input('contact_no'))) $values['contact_no']=\App\Support\PhilippineMobile::normalize($request->input('contact_no'));
        $request->merge($values);
    }

    public function readable(?string $path): bool
    {
        if(!$path || !SensitiveDocuments::safe($path) || !SensitiveDocuments::directory($path)) return false;
        $full=Storage::disk('sensitive_documents')->path($path);$root=realpath(Storage::disk('sensitive_documents')->path(''));$resolved=realpath($full);
        return $root && $resolved && !is_link($full) && str_starts_with($resolved,$root.DIRECTORY_SEPARATOR) && is_file($full) && is_readable($full);
    }

    public function rules(string $role, ?string $ownership, ?User $account=null): array
    {
        $file=fn(string $field,string $mimes='jpg,jpeg,png,pdf')=>[($account && $this->readable($account->{$field}))?'nullable':'required','file','mimes:'.$mimes,'max:5120'];
        $rules=['selfie_file'=>$file('selfie_file','jpg,jpeg,png')];
        if($role==='hub_staff') return $rules+['id_file'=>$file('id_file'),'id_type_id'=>'required|integer|exists:id_types,id'];
        $unique=fn(string $field)=>function($attribute,$value,$fail)use($field,$account){
            if(User::where('status','!=','rejected')->when($account,fn($q)=>$q->where('id','!=',$account->id))->whereRaw('LOWER(TRIM('.$field.')) = ?',[mb_strtolower(trim($value))])->exists()) $fail('This identifier is already registered to another account.');
        };
        $rules+=['vehicle_type'=>'required|exists:vehicle_types,slug','vehicle_ownership'=>'required|in:own,company','license_number'=>['required','string','regex:/^[A-Z]\d{2}-\d{2}-\d{6}$/D',$unique('license_number')],
            'license_expiry'=>'required|date|after_or_equal:today','license_file'=>$file('license_file')];
        if($ownership==='own') $rules+=['vehicle_brand'=>'required|string|max:100','vehicle_model'=>'required|string|max:100','plate_number'=>['required','string','max:20',$unique('plate_number')],'or_file'=>$file('or_file'),'cr_file'=>$file('cr_file')];
        return $rules;
    }

    public function files(string $role, ?string $ownership): array
    {
        return $role==='hub_staff'?['id_file'=>'id_files','selfie_file'=>'selfie_files']:
            (['license_file'=>'license_files','selfie_file'=>'selfie_files']+($ownership==='own'?['or_file'=>'vehicle_docs','cr_file'=>'vehicle_docs']:[]));
    }

    public function store(Request $request,string $role,array $data,?User $account=null): array
    {
        foreach($this->files($role,$data['vehicle_ownership']??null) as $field=>$directory){
            unset($data[$field]);
            if($request->hasFile($field)) $data[$field]=app(MediaLifecycle::class)->store($request->file($field),$directory,'public');
        }
        if($role==='rider'){
            $data['id_file']=$data['license_file']??$account?->license_file;
            $data['id_type_id']=DB::table('id_types')->where('name',"Driver's License")->value('id');
            if(!$data['id_type_id']) throw ValidationException::withMessages(['license_file'=>"The Driver's License identity type must be configured before submitting an application."]);
        }
        return $data;
    }

    public function assertReadable(User $user,string $role): void
    {
        foreach(array_keys($this->files($role,$user->vehicle_ownership)) as $field) if(!$this->readable($user->{$field})) throw ValidationException::withMessages([$field=>'A readable private document is required before approval. Submit a genuine replacement.']);
        if($role==='rider' && $user->id_file!==$user->license_file) throw ValidationException::withMessages(['license_file'=>'The rider licence must also be the recorded identity document.']);
    }
}
