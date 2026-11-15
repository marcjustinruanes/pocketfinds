@php
 $returns=\Illuminate\Support\Facades\DB::table('commerce_returns')->orderByDesc('id')->get();
@endphp
<div class="card platform-accounts-card"><div class="card-pad"><div class="table-wrap"><table class="dtable" id="returnsTable" data-platform-filter-table>
<thead><tr><th>Order</th><th>Reason</th><th>Refund amount</th><th>@include('admin.partials.column-filter',['filterTable'=>'returnsTable','filterLabel'=>'Status','filterField'=>'status','filterOptions'=>['all'=>'All statuses','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','escalated'=>'Escalated','received'=>'Received','refunded'=>'Refunded']])</th><th class="platform-manage-heading">Manage</th></tr></thead>
<tbody>
@forelse($returns as $return)
<tr data-status="{{ $return->status }}"><td>{{ $return->order_id }}</td><td>{{ $return->reason }}</td><td>PHP {{ number_format($return->amount,2) }}</td><td><span class="stamp stamp-{{ $return->status }}">{{ ucfirst($return->status) }}</span></td><td><button type="button" class="btn platform-detail-button" data-modal-open="returnDetails-{{ $return->id }}" aria-label="Manage return request"><span aria-hidden="true">&#8599;</span></button></td></tr>
@push('return-modals')
<div class="modal-overlay" id="returnDetails-{{ $return->id }}"><section class="modal modal-lg" role="dialog" aria-modal="true" aria-label="Return request details"><header class="modal-head"><h3>Return & refund request</h3><button type="button" class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button></header><div class="modal-body platform-return-details">@include('account.partials.return-case')</div><footer class="modal-foot"><button type="button" class="btn btn-outline" data-modal-close>Close</button></footer></section></div>
@endpush
@empty
<tr><td colspan="5"><div class="empty"><h3>No return requests</h3><p>Order return and refund requests will appear here.</p></div></td></tr>
@endforelse
</tbody></table></div></div></div>
@stack('return-modals')
