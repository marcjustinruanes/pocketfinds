@extends('seller.layout')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-sub', 'Sales, verified payment activity and COD payouts')

@section('content')
<p>Sales use completed orders updated in the selected period. Payment activity uses its actual verification, collection or payment date. Shipping and discounts are already included in buyer order values; they are not deducted again. No platform commission is charged.</p>
{{-- Date range picker --}}
<div class="card" style="margin-bottom:18px">
  <form method="GET" action="{{ route('seller.reports') }}" class="card-pad" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <span style="font-size:13px;font-weight:650;color:var(--muted)">Date Range</span>
    <div class="form-row" style="margin:0;display:flex;align-items:center;gap:8px">
      <label for="report-from" style="font-size:12px;color:var(--muted);margin:0">From</label>
      <input id="report-from" type="date" name="from" value="{{ $from->format('Y-m-d') }}" style="border:1px solid var(--border);border-radius:9px;padding:8px 12px;font-size:13px;background:#fff">
    </div>
    <div class="form-row" style="margin:0;display:flex;align-items:center;gap:8px">
      <label for="report-to" style="font-size:12px;color:var(--muted);margin:0">To</label>
      <input id="report-to" type="date" name="to" value="{{ $to->format('Y-m-d') }}" style="border:1px solid var(--border);border-radius:9px;padding:8px 12px;font-size:13px;background:#fff">
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      @php
      $presets = [
        'Today'      => [now()->startOfDay(), now()->endOfDay()],
        'This Week'  => [now()->startOfWeek(), now()->endOfWeek()],
        'This Month' => [now()->startOfMonth(), now()->endOfMonth()],
        'Last Month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
        'This Year'  => [now()->startOfYear(), now()->endOfYear()],
      ];
      @endphp
      @foreach($presets as $label => [$rangeFrom, $rangeTo])
      <a href="{{ route('seller.reports', ['from' => $rangeFrom->format('Y-m-d'), 'to' => $rangeTo->format('Y-m-d')]) }}"
         class="btn btn-sm btn-outline {{ $from->isSameDay($rangeFrom) && $to->isSameDay($rangeTo) ? 'active' : '' }}"
         style="{{ $from->isSameDay($rangeFrom) && $to->isSameDay($rangeTo) ? 'border-color:var(--pink);color:var(--pink-dark)' : '' }}">{{ $label }}</a>
      @endforeach
    </div>
    <button type="submit" class="btn btn-primary" style="margin-left:auto">@include('seller.partials.icon', ['name' => 'chart', 'size' => 14]) Generate</button>
  </form>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Buyer order value</div><div class="value">₱{{ number_format($totalRevenue, 2) }}</div><div class="delta up">This period</div></div>
  <div class="kpi"><div class="label">Item sales before discounts</div><div class="value">PHP {{ number_format($paymentReport['metrics']['item_sales']/100, 2) }}</div><div class="delta">Excludes shipping</div></div>
  <div class="kpi"><div class="label">Orders Completed</div><div class="value">{{ number_format($ordersCompleted) }}</div><div class="delta">This period</div></div>
  <div class="kpi"><div class="label">Avg. Order Value</div><div class="value">₱{{ number_format($avgOrderValue, 2) }}</div><div class="delta">Per order</div></div>
</div>

<div class="dash-grid payment-report-layout">
  <div class="stack">
    {{-- Revenue chart --}}
    <div class="card">
      <div class="card-head"><div><h2>Completed order value over time</h2><p>Completed orders by last update date; not payment receipt dates</p></div></div>
      <div class="card-pad">
        <div class="chart-area">
          @foreach($revenueChart as $amount)
          <div class="chart-bar {{ $amount === $chartMax ? 'highlight' : '' }}" style="height:{{ max(3, ($amount / $chartMax) * 100) }}%" title="₱{{ number_format($amount, 2) }}"></div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Top products --}}
    <div class="card">
      <div class="card-head"><div><h2>Top Products</h2><p>Best performing items this period</p></div></div>
      <div class="card-pad" style="padding:0">
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>#</th><th>Product</th><th>Units Sold</th><th>Item value before discounts</th></tr></thead>
          <tbody>
            @forelse($topProducts as $product)
            <tr>
              <td style="color:var(--muted);font-family:var(--font-mono)">{{ $loop->iteration }}</td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="inv-img" style="width:32px;height:32px">@include('seller.partials.icon', ['name' => 'bag', 'size' => 16])</div>
                  {{ $product['name'] }}
                </div>
              </td>
              <td class="mono">{{ $product['units'] }}</td>
              <td class="mono">₱{{ number_format($product['revenue'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4"><div class="empty" style="padding:24px 20px"><h3>No sales data yet</h3></div></td></tr>
            @endforelse
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    @include('reports.payment-summary',['report'=>$paymentReport])

    {{-- Sales by category --}}
    <div class="card">
      <div class="card-head"><h2>Item sales by category</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        @forelse($salesByCategory as $cat => $amount)
        <div style="display:flex;align-items:center;gap:10px;font-size:12px">
          <span style="flex:1;color:var(--text)">{{ $cat }}</span>
          <div style="width:100px;height:6px;background:var(--border);border-radius:3px;overflow:hidden">
            <div style="height:100%;background:var(--pink-line);width:{{ round($amount / $categoryTotal * 100) }}%;border-radius:3px"></div>
          </div>
          <span class="mono" style="width:40px;text-align:right;color:var(--muted)">{{ round($amount / $categoryTotal * 100) }}%</span>
        </div>
        @empty
        <p style="font-size:12.5px;color:var(--muted);margin:0">No sales data yet.</p>
        @endforelse
      </div>
    </div>

    {{-- Performance metrics --}}
    <div class="card">
      <div class="card-head"><h2>Performance</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        @foreach([
          ['Order Fulfillment Rate', $fulfillmentRate !== null ? $fulfillmentRate . '%' : '—'],
          ['Customer Satisfaction', $satisfaction !== null ? $satisfaction . ' / 5' : '—'],
        ] as [$metric,$val])
        <div style="display:flex;justify-content:space-between;font-size:13px">
          <span style="color:var(--muted)">{{ $metric }}</span>
          <span class="mono" style="font-weight:650">{{ $val }}</span>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</div>
@endsection
