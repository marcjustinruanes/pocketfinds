<form method="GET" class="commission-filter">
    <label>History from<input type="date" name="{{ $history }}_from" value="{{ request($history.'_from') }}"></label>
    <label>History to<input type="date" name="{{ $history }}_to" value="{{ request($history.'_to') }}"></label>
    <label>History status<select name="{{ $history }}_status"><option value="">All statuses</option>@foreach($kind==='credits'?['pending','paid']:['draft','pending','review','rejected','verified','cancelled'] as $status)<option @selected(request($history.'_status')===$status)>{{ $status }}</option>@endforeach</select></label>
    <button class="btn" type="submit">Filter history</button>
    <a class="btn btn-outline" href="{{ route('commissions.money-export',['kind'=>$kind,'from'=>request($history.'_from'),'to'=>request($history.'_to'),'status'=>request($history.'_status')]) }}">Export filtered CSV</a>
</form>
