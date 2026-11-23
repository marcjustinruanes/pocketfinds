@php($u=auth()->user())
<div class="return-case" style="padding:12px 0 16px;border-top:1px solid #f0e8ed">
    <h3 style="margin:0 0 4px">Order #{{ strtoupper(substr($return->order_id,0,8)) }} · {{ ucfirst($return->status) }}</h3>
    <p style="margin:4px 0"><strong>{{ ucfirst(str_replace('_',' ',$return->reason)) }}:</strong> {{ $return->description }}</p>
    <p style="margin:4px 0">
        ₱{{ number_format($return->amount,2) }} ·
        @php($dest=explode('/',$return->refund_destination,2))
        @if(count($dest)===2)
            <strong>{{ trim($dest[0]) }}</strong> — {{ trim($dest[1]) }}
        @else
            {{ $return->refund_destination }}
        @endif
    </p>
    @if($return->decision_reason)
        <p style="margin:4px 0"><strong>Decision note:</strong> {{ $return->decision_reason }}</p>
    @endif
    @if(count(json_decode($return->photos,true)??[]))
        <p style="margin:8px 0 4px"><strong>Evidence:</strong>
        @foreach(json_decode($return->photos,true) as $index=>$photo)
            <a href="{{ route('commerce.return.photo',[$return->id,$index]) }}" target="_blank" rel="noopener">Photo {{ $index+1 }}</a>{{ !$loop->last ? ' · ' : '' }}
        @endforeach
        </p>
    @endif
    @if($return->status==='pending' || ($u->is_admin && $return->status==='escalated'))
        <form style="display:grid;gap:12px;margin-top:12px" method="POST" action="{{ route('commerce.return.action',$return->id) }}">
            @csrf
            <label class="form-row">Decision
                <select class="select" name="action">
                    <option value="approve">Approve return</option>
                    <option value="reject">Reject</option>
                </select>
            </label>
            <label class="form-row">Reason <span style="color:var(--text-muted);font-size:12px">(required when rejecting)</span>
                <textarea name="reason" minlength="10" maxlength="500"></textarea>
            </label>
            <button class="btn btn-primary">Submit decision</button>
        </form>
    @endif
    @if(!$u->is_admin && $return->status==='approved')
        <form style="display:grid;gap:12px;margin-top:12px" method="POST" action="{{ route('commerce.return.action',$return->id) }}">
            @csrf
            <input type="hidden" name="action" value="received">
            <p style="margin:0">Confirm only after the returned goods arrive. Stock is restored once.</p>
            <button class="btn btn-primary">Confirm returned items received</button>
        </form>
    @endif
    @if($u->is_admin && $return->status==='received')
        <form style="display:grid;gap:12px;margin-top:12px" method="POST" action="{{ route('commerce.return.action',$return->id) }}">
            @csrf
            <input type="hidden" name="action" value="refunded">
            <label class="form-row">Completed repayment transaction reference
                <input name="reference" required maxlength="255">
            </label>
            <p style="margin:0">Record this after the actual repayment is completed.</p>
            <button class="btn btn-primary">Record completed repayment</button>
        </form>
    @endif
    @if($return->refund_reference)
        <p style="margin:8px 0 0"><strong>Repayment reference:</strong> {{ $return->refund_reference }}</p>
    @endif
</div>
