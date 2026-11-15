<tr>
<td class="mono">{{ substr($batch->id,0,8) }}<small class="assignment-route-detail">{{ $batch->members_count }} {{ \Illuminate\Support\Str::plural('parcel',$batch->members_count) }}</small></td>
<td>Hub transfer</td>
<td>{{ $batch->from_hub }}, {{ $batch->from_province }}<small class="assignment-route-detail">&rarr; {{ $batch->to_hub }}, {{ $batch->to_province }}</small></td>
<td><span class="stamp stamp-{{ $batch->status }}">{{ $batch->label() }}</span></td>
<td>@if($batch->rider)<strong>{{ $batch->rider->given_names }} {{ $batch->rider->last_name }}</strong>@endif
<a class="btn btn-sm btn-outline" href="{{ route('logistics.transfers.show',$batch->id) }}" data-modal-open="transferDetailsModal" data-transfer-details-url="{{ route('logistics.transfers.show',$batch->id) }}" aria-haspopup="dialog">{{ auth()->user()->isHubStaff() && auth()->user()->logistics_hub_id == $batch->origin_hub_id && $batch->status === 'approved' ? 'Assign rider' : (auth()->user()->isLogisticsAdmin() && $batch->status === 'pending' ? 'Approve / Reject' : 'Details') }}</a></td>
<td>{{ $batch->rider_id ? 'Assigned' : ($batch->status === 'pending' ? 'Awaiting approval' : (in_array($batch->status,['rejected','cancelled']) ? 'Closed' : 'Unassigned')) }}</td>
<td>{{ $batch->dispatched_at?->format('M d, g:i A') ?? '?' }}</td>
</tr>
