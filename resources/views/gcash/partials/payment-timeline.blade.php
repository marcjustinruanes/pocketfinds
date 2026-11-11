@php
 $timelineEvents=$paymentHistory->values()->map(fn($event)=>[
  'title'=>match($event->event){'instructions'=>'Payment details sent','platform_instructions'=>'Platform payment details','admin_verified'=>'Admin approved payment','admin_rejected'=>'Admin rejected proof','admin_short_payment'=>'Admin requested top-up','verified'=>'Payment verified','proof_submitted'=>'Proof submitted','order_placed'=>'Order placed',default=>ucfirst(str_replace('_',' ',$event->event))},
  'timestamp'=>$event->created_at,'reason'=>$event->reason,'recorded'=>true,
  'icon'=>match($event->event){'created'=>'bag','reservation_started'=>'package','instructions','platform_instructions'=>'clipboard-check','proof_submitted','payment_reported'=>'receipt','verified','admin_verified','admin_short_payment','stock_reacquired'=>'shield','order_placed'=>'package-check','rejected','admin_rejected','expired','abandoned','repayment_required','product_rejected'=>'ban',default=>'clipboard-check'},
 ]);
 if(!$ordered && !$inactive && $p->payment_state!=='manual_resolution'){
  foreach([
   ['Payment details',!(bool)$p->instructions],
   ['Submit proof',!$paymentHistory->contains('event','proof_submitted')&&!$verified],
   [$platformFlow?'Admin verification':'Seller verification',!$verified],
   ['Order confirmation',!$platformFlow],
  ] as [$title,$pending]){
   if($pending)$timelineEvents->push(['title'=>$title,'timestamp'=>null,'reason'=>null,'recorded'=>false,'icon'=>match($title){'Payment details'=>'clipboard-check','Submit proof'=>'receipt','Seller verification','Admin verification'=>'shield',default=>'package-check'}]);
  }
 }
 $lastRecorded=$timelineEvents->where('recorded',true)->keys()->last();
@endphp
<section class="gcash-combined-timeline order-tracking order-tracking-horizontal" aria-label="Payment progress and history">
 <h3>Payment timeline</h3>
 <ol class="tracking-steps">
 @foreach($timelineEvents as $event)
 @php
  $stageState=!$event['recorded']?'future':(!$ordered&&$loop->index===$lastRecorded?'current':'completed');
 @endphp
 <li class="tracking-step is-{{ $stageState }}" @if($stageState==='current') aria-current="step" @endif>
  <span class="tracking-dot" aria-hidden="true">@include('buyer.partials.icon',['name'=>$event['icon'],'size'=>21])</span>
  <strong>{{ $event['title'] }}</strong>
  @if($event['timestamp'])<time class="gcash-timeline-time" datetime="{{ \Illuminate\Support\Carbon::parse($event['timestamp'])->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($event['timestamp'])->format('M j, Y · g:i A') }}</time>@endif
  @if($event['reason'])<p class="gcash-timeline-reason">{{ $event['reason'] }}</p>@endif
 </li>
 @endforeach
 </ol>
</section>
