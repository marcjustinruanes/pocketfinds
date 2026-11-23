@php
    $editFields = ['region','province','municipality','barangay','postal_code','house_no','street','given_names','last_name','middle_name','suffix','sex','birthday','email','contact_no','username','license_number','license_expiry','vehicle_type','vehicle_ownership','vehicle_brand','vehicle_model','plate_number','id_type_id'];
    $editValues = [];
    foreach ($editFields as $field) {
        $value = $editingAccount->{$field};
        if ($value instanceof \BackedEnum) $value = $value->value;
        if ($value instanceof \DateTimeInterface) $value = $value->format('Y-m-d');
        $editValues[$field] = old($field, $value);
    }
    $editDocuments = [];
    $documentService = app(\App\Services\SensitiveDocuments::class);
    foreach (['license_file','id_file','selfie_file','nbi_clearance_file','or_file','cr_file','resume_file'] as $field) {
        $editDocuments[$field] = $editingAccount->{$field} ? $documentService->url($editingAccount->{$field}) : null;
    }
    $editConfig = ['values'=>$editValues,'documents'=>$editDocuments,'company'=>$editingAccount->business_name,'hub'=>$editingAccount->logistics_hub_id,'onboarding'=>in_array($editingAccount->status,['pending','interview'],true),'activationPending'=>$editingAccount->account_type === 'rider' && app(\App\Services\RiderActivation::class)->pending($editingAccount),'action'=>route('logistics.accounts.update',$editingAccount->id,false)];
@endphp
<script id="personnel-edit-data" type="application/json">@json($editConfig)</script>
<script src="{{ asset('js/personnel-registration-edit.js') }}?v={{ filemtime(public_path('js/personnel-registration-edit.js')) }}"></script>
