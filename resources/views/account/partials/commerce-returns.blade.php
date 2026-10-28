@php($u=auth()->user())
@php($returns=\Illuminate\Support\Facades\DB::table('commerce_returns')->when(!$u->is_admin,fn($q)=>$q->where('seller_id',$u->id))->latest()->get())
<div class="card" id="returns-refunds"><div class="card-head"><h2>Returns and refunds</h2></div><div class="card-pad">@forelse($returns as $return)@include('account.partials.return-case')@empty<p>No return requests.</p>@endforelse</div></div>
