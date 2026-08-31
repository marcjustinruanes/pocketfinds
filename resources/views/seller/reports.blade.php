@extends('seller.layout')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-sub', 'Financial, profit and sales performance reports')

@section('content')
{{-- Date range picker --}}
<div class="card" style="margin-bottom:18px">
  <form method="GET" action="{{ route('seller.reports') }}" class="card-pad" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <span style="font-size:13px;font-weight:650;color:var(--muted)">Date Range</span>
    <div class="form-row" style="margin:0;display:flex;align-items:center;gap:8px">
      <label style="font-size:12px;color:var(--muted);margin:0">From</label>
      <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" style="border:1px solid var(--border);border-radius:9px;padding:8px 12px;font-size:13px;background:#fff">
    </div>
    <div class="form-row" style="margin:0;display:flex;align-items:center;gap:8px">
      <label style="font-size:12px;color:var(--muted);margin:0">To</label>
      <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" style="border:1px solid var(--border);border-radius:9px;padding:8px 12px;font-size:13px;background:#fff">
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
  <div class="kpi"><div class="label">Total Revenue</div><div class="value">₱{{ number_format($totalRevenue, 2) }}</div><div class="delta up">This period</div></div>
  <div class="kpi"><div class="label">Net Profit</div><div class="value">₱{{ number_format($netProfit, 2) }}</div><div class="delta up">After fees</div></div>
  <div class="kpi"><div class="label">Orders Completed</div><div class="value">{{ number_format($ordersCompleted) }}</div><div class="delta">This period</div></div>
  <div class="kpi"><div class="label">Avg. Order Value</div><div class="value">₱{{ number_format($avgOrderValue, 2) }}</div><div class="delta">Per order</div></div>
</div>

<div class="dash-grid">
  <div class="stack">
    {{-- Revenue chart --}}
    <div class="card">
      <div class="card-head"><div><h2>Revenue Over Time</h2><p>Daily sales for selected period</p></div></div>
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
        <table class="tbl">
          <thead><tr><th>#</th><th>Product</th><th>Units Sold</th><th>Revenue</th></tr></thead>
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

  <div class="stack">
    {{-- Financial summary --}}
    <div class="card">
      <div class="card-head"><h2>Financial Summary</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        @foreach([
          ['Gross Revenue', '₱' . number_format($totalRevenue, 2), ''],
          ['Platform Commission', '- ₱' . number_format($commission, 2), 'color:var(--danger)'],
          ['Shipping Fees', '- ₱' . number_format($shippingFees, 2), 'color:var(--danger)'],
          ['Voucher Discounts', '- ₱' . number_format($discounts, 2), 'color:var(--danger)'],
          ['Net Profit', '₱' . number_format($netProfit, 2), 'color:var(--success);font-weight:700;font-size:15px'],
        ] as [$label,$val,$style])
        <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;{{ $label==='Net Profit'?'border-top:2px solid var(--border);margin-top:4px;padding-top:12px':'' }}">
          <span style="color:var(--muted)">{{ $label }}</span>
          <span class="mono" style="{{ $style }}">{{ $val }}</span>
        </div>
        @endforeach
      </div>
    </div>

    {{-- Sales by category --}}
    <div class="card">
      <div class="card-head"><h2>Sales by Category</h2></div>
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
