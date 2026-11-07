@extends($actor->is_admin ? 'admin.layout' : ($actor->account_type==='seller'?'seller.layout':'logistics.layout'))
@section('title','Commission payment messages')
@section('page-title','Commission payment messages')
@section('page-sub','Discuss manual payment evidence and corrections')
@push('head')<link rel="stylesheet" href="{{ asset('css/commissions.css') }}?v={{ filemtime(public_path('css/commissions.css')) }}">@endpush
@section('content')
<div class="commission-workspace">
@if(session('success'))<p class="commission-feedback is-success" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="commission-feedback is-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="card commission-panel"><div class="card-head"><h2>Manual {{ $kind==='recovery'?'payment recovery':strtoupper($record->method).' payment' }}</h2><span class="commission-badge status-{{ $record->status }}">{{ ucfirst($record->status) }}</span></div>
<div class="card-pad"><p>Record {{ $record->id }}<br>Reference: {{ $record->reference??'Not sent' }}<br>Amount: PHP {{ number_format($record->amount_cents/100,2) }}</p>
<p>Messages and uploaded evidence never collect commission. Platform Admin must verify the actual receipt.</p>
@if($record->proof_path)<p><a href="{{ route('commissions.evidence',[$kind,$record->id]) }}">Original private payment proof</a></p>@endif
<p><a href="{{ route('commissions.index',[$kind.'_id'=>$record->id]) }}#commission-{{ $kind }}-{{ $record->id }}">Open payment actions and history</a></p>
@if($kind==='payment')@include('commissions.payment-review',['p'=>$record])@endif
</div></section>
<section class="card commission-panel"><div class="card-head"><h2>Messages</h2><span>PHT (Asia/Manila)</span></div><div class="card-pad">
@forelse($messages as $message)
<article class="commission-record"><p><strong>{{ $message->sender_label }} — {{ $message->sender_role }}</strong><br><time>{{ \Carbon\Carbon::parse($message->created_at,config('app.timezone'))->timezone('Asia/Manila')->format('Y-m-d H:i:s') }} PHT</time> / {{ ucfirst(str_replace('_',' ',$message->kind)) }}</p>
<p class="commission-message-text">{{ $message->body }}</p>
@if($message->proof_path)<p><a href="{{ route('commissions.message-proof',$message->id) }}">Private correction evidence</a> (original payment proof is preserved)</p>@endif
</article>
@empty<p>No messages yet.</p>@endforelse
{{ $messages->links() }}
<form method="POST" action="{{ route('commissions.message',[$kind,$record->id]) }}" enctype="multipart/form-data" data-confirm="Send this payment message?">@csrf
<input type="hidden" name="submission_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>Message<textarea name="message" maxlength="4000" required></textarea></label>
<label>Correction evidence (optional, image or PDF, up to 5 MB)<input name="proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
<button class="btn btn-primary" type="submit">Send message</button>
</form></div></section></div>
@endsection
@push('scripts')<script src="{{ asset('js/commissions.js') }}?v={{ filemtime(public_path('js/commissions.js')) }}" defer></script>@endpush
