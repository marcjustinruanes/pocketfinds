@php
 $meta = json_decode($evidence->before,true) ?? [];
@endphp
<div class="gcash-evidence-with-note">
 @include('gcash.partials.proof-card',['proofLabel'=>$evidence->event === 'admin_received_proof' ? 'Admin proof of amount received' : 'Admin proof of returned payment','proofUrl'=>route('gcash.adminEvidence',[$p->id,$evidence->id]),'proofMime'=>$meta['mime'] ?? '', 'proofDate'=>$evidence->created_at])

</div>
