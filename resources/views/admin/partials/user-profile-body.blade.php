@php
  $personnelRole = app(\App\Services\HubHiring::class)->role($user);
  $founder = \App\Services\CompanyOwnership::founder($user);
  $sellerType = $user->seller_type instanceof \App\Enums\SellerType ? $user->seller_type : \App\Enums\SellerType::tryFrom((string)$user->seller_type);
  $suffix = $user->suffix instanceof \BackedEnum ? $user->suffix->value : $user->suffix;
  $identityName = $user->id_type_id ? \Illuminate\Support\Facades\DB::table('id_types')->where('id',$user->id_type_id)->value('name') : null;
  $sections = [
    'Personal information'=>[
      'First name'=>$user->given_names,'Middle name'=>$user->middle_name,'Last name'=>$user->last_name,'Suffix'=>$suffix,
      'Sex'=>$user->sex ? ucfirst($user->sex) : null,'Birthday'=>$user->birthday?->format('M j, Y'),
    ],
    'Account & contact'=>[
      'Username'=>$user->username,'Email address'=>$user->email,'Contact number'=>$user->contact_no,
      'Sign-in method'=>ucfirst($user->auth_method ?? ''),'Joined'=>$user->created_at?->format('M j, Y'),
    ],
    'Address'=>[
      'Region'=>$user->region,'Province'=>$user->province,'City / municipality'=>$user->municipality,
      'Barangay'=>$user->barangay,'House number'=>$user->house_no,'Street'=>$user->street,'Postal code'=>$user->postal_code,
    ],
  ];
  if ($user->account_type==='seller') $sections['Business information']=[
    'Business name'=>$user->business_name,'Seller type'=>$sellerType?->label(),'TIN'=>$user->tin,
    'Shop category'=>collect([$user->category?->name,$user->category_other])->filter()->implode(', '),
  ];
  if ($founder) $sections['Company & representative']=[
    'Company name'=>$user->business_name,'SEC registration number'=>$user->sec_registration_no,'TIN'=>$user->tin,
    'Position'=>$user->position,'Representative email'=>$user->representative_email,'Representative contact'=>$user->representative_contact_no,
    'Service regions'=>implode(', ', $user->service_regions ?? []),
  ];
  if ($personnelRole) $sections['Company & hub assignment']=[
    'Company'=>$user->business_name,'Role'=>$personnelRole==='hub_staff'?'Hub staff':'Rider',
    'Assigned hub'=>$user->logisticsHub ? collect([$user->logisticsHub->municipality,$user->logisticsHub->province])->filter()->implode(', ') : null,
  ];
  if ($personnelRole==='rider') $sections['Vehicle & licence']=[
    'Vehicle type'=>\Illuminate\Support\Str::headline($user->vehicle_type ?? ''),
    'Ownership'=>match($user->vehicle_ownership){'own'=>'Own vehicle','company'=>'Company vehicle',default=>null},
    'Brand'=>$user->vehicle_brand,'Model'=>$user->vehicle_model,'Plate number'=>$user->plate_number,
    'Licence number'=>$user->license_number,'Licence expiry'=>$user->license_expiry?->format('M j, Y'),
  ];
  $documents = ['selfie_file'=>'Selfie'];
  if ($personnelRole==='rider') {
    $documents += ['license_file'=>"Driver's License",'nbi_clearance_file'=>'NBI clearance'];
    if ($user->vehicle_ownership==='own') $documents += ['or_file'=>'Official receipt (OR)','cr_file'=>'Certificate of registration (CR)'];
    $documents += ['resume_file'=>'Resume / CV (optional)'];
  } else {
    $documents = ['id_file'=>'Government-issued ID'] + $documents;
    if ($personnelRole==='hub_staff') $documents += ['resume_file'=>'Resume / CV (optional)'];
  }
  if ($user->account_type==='seller') {
    foreach (\App\Support\SellerDocuments::LABELS as $field=>$label) {
      if (in_array($field,\App\Support\SellerDocuments::required($user->seller_type),true) || $user->{$field}) $documents[$field]=$label;
    }
    if ($user->business_permit_file) $documents['business_permit_file']='Business permit';
  }
  if ($founder) $documents += ['company_logo'=>'Company logo','business_permit_file'=>'Business permit'] + \App\Support\LogisticsDocuments::LABELS;
@endphp
<div class="platform-registration-record">
@foreach($sections as $sectionTitle=>$fields)
<section class="section-card platform-registration-section">
  <div class="section-head"><span class="platform-registration-step">{{ $loop->iteration }}</span><span>{{ $sectionTitle }}</span></div>
  <dl class="platform-profile-fields">
    @foreach($fields as $label=>$value)
      <div><dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : 'Not provided' }}</dd></div>
    @endforeach
  </dl>
</section>
@endforeach
<details class="section-card platform-document-section" open>
  <summary>Submitted documents<span>{{ collect($documents)->filter(fn($label,$field)=>filled($user->{$field}))->count() }} uploaded</span></summary>
  <div class="platform-identity-type"><span>ID type</span><strong>{{ $personnelRole==='rider' ? "Driver's License" : ($identityName ?: 'Not provided') }}</strong></div>
  <div class="doc-grid">
    @foreach($documents as $field=>$label)
      <x-admin-doc-thumb :path="$user->{$field}" :label="$label" />
    @endforeach
  </div>
</details>
</div>
