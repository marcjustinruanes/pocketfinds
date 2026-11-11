@foreach($review->follow_ups ?? [] as $followUp)
<div class="review-follow-up" style="margin-top:16px;padding-top:14px;border-top:1px solid #eee3ea">
 <strong style="font-size:12px;color:#a13a72">Follow-up review</strong><small style="display:block;margin-top:4px;color:#927c89;font-size:10px">{{ \Illuminate\Support\Carbon::parse($followUp['created_at'])->format('M d, Y') }}</small>
 @if(!empty($followUp['comment']))<p style="font-size:13px;line-height:1.7;white-space:pre-wrap;overflow-wrap:anywhere">{{ $followUp['comment'] }}</p>@endif
 <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
 @foreach($followUp['media'] ?? [] as $attachment)
 @if($attachment['type']==='video')<video controls preload="metadata" src="{{ Storage::disk('public')->url($attachment['path']) }}" style="width:90px;height:90px;border-radius:8px" aria-label="Follow-up review video"></video>
 @else<a href="{{ Storage::disk('public')->url($attachment['path']) }}" target="_blank" rel="noopener"><img src="{{ Storage::disk('public')->url($attachment['path']) }}" alt="Follow-up review photo" loading="lazy" width="90" height="90" style="object-fit:cover;border-radius:8px"></a>@endif
 @endforeach
 </div>
</div>
@endforeach
