@php($custody = app(\App\Services\ParcelCustody::class)->describe($parcel,auth()->user()))
<div class="parcel-custody"><strong>{{ $custody['label'] }}</strong><div>{{ $custody['responsible'] }}</div>@if($custody['at'])<small>Evidence: {{ \Carbon\CarbonImmutable::parse($custody['at'])->setTimezone('Asia/Manila')->format('M j, Y H:i') }} PHT</small>@endif<p>{{ $custody['next'] }}</p><small>{{ $custody['note'] }}</small></div>
@once
@push('head')<style>.parcel-custody{margin:10px 0;padding:12px;border-left:3px solid #c83883;background:rgba(200,56,131,.04);border-radius:8px;min-width:240px;max-width:360px;white-space:normal;font:13px/1.6 Inter,system-ui,sans-serif;overflow-wrap:anywhere}.parcel-custody strong{display:block;color:var(--text)}.parcel-custody p{margin:6px 0}.parcel-custody small{display:block;color:var(--muted)}</style>@endpush
@endonce
