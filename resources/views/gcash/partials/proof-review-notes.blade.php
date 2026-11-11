@php $shownNotes = []; @endphp
@foreach($noteEvidence as $noteProof)
 @php
  $noteEvents = match ($noteProof->event) {
   'admin_received_proof' => ['admin_short_payment','admin_verified'],
   'admin_rejection_refund_proof' => ['admin_rejected'],
   'admin_refund_proof' => ['overpayment_refunded'],
   default => [],
  };
  $proofNote = $reviewNotes->last(fn($row) => $row->id < $noteProof->id && $row->actor_id == $noteProof->actor_id && in_array($row->event,$noteEvents,true));
 @endphp
 @if(filled($proofNote?->reason) && !in_array($proofNote->id,$shownNotes,true))
  <p class="order-payment-error gcash-evidence-note gcash-proof-pair-note" role="status"><strong>Admin’s note:</strong> {{ $proofNote->reason }}</p>
  @php $shownNotes[] = $proofNote->id; @endphp
 @endif
@endforeach
@foreach(($standaloneNotes ?? collect()) as $standaloneNote)
 @if(filled($standaloneNote->reason) && !in_array($standaloneNote->id,$shownNotes,true))
  <p class="order-payment-error gcash-evidence-note gcash-proof-pair-note" role="status"><strong>Admin’s note:</strong> {{ $standaloneNote->reason }}</p>
 @endif
@endforeach
