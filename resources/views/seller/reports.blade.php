@extends('seller.layout')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-sub', 'Financial, profit and sales performance reports')

@section('content')
{{-- Date range picker --}}
<div class="card" style="margin-bottom:18px">
  <div class="card-pad" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <span style="font-size:13px;font-weight:650;color:var(--muted)">Date Range</span>
    <div class="form-row" style="margin:0;display:flex;align-items:center;gap:8px">
      <label style="font-size:12px;color:var(--muted);margin:0">From</label>
      <input type="date" value="{{ now()->startOfMonth()->format('Y-m-d') }}" style="border:1px solid var(--border);border-radius:9px;padding:8px 12px;font-size:13px;background:#fff">
    </div>
    <div class="form-row" style="margin:0;display:flex;align-items:center;gap:8px">
      <label style="font-size:12px;color:var(--muted);margin:0">To</label>
      <input type="date" value="{{ now()->format('Y-m-d') }}" style="border:1px solid var(--border);border-radius:9px;padding:8px 12px;font-size:13px;background:#fff">
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      @foreach(['Today','This Week','This Month','Last Month','This Year'] as $preset)
      <button class="btn btn-sm btn-outline {{ $preset==='This Month'?'active':'' }}" style="{{ $preset==='This Month'?'border-color:var(--pink);color:var(--pink-dark)':'' }}">{{ $preset }}</button>
      @endforeach
    </div>
    <button class="btn btn-primary" style="margin-left:auto">@include('seller.partials.icon', ['name' => 'chart', 'size' => 14]) Generate</button>
    <button class="btn btn-outline">@include('seller.partials.icon', ['name' => 'download', 'size' => 14]) Export</button>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Total Revenue</div><div class="value">₱0</div><div class="delta up">This period</div></div>
  <div class="kpi"><div class="label">Net Profit</div><div class="value">₱0</div><div class="delta up">After fees</div></div>
  <div class="kpi"><div class="label">Orders Completed</div><div class="value">0</div><div class="delta">This period</div></div>
  <div class="kpi"><div class="label">Avg. Order Value</div><div class="value">₱0</div><div class="delta">Per order</div></div>
</div>

<div class="dash-grid">
  <div class="stack">
    {{-- Revenue chart --}}
    <div class="card">
      <div class="card-head"><div><h2>Revenue Over Time</h2><p>Daily sales for selected period</p></div></div>
      <div class="card-pad">
        <div class="chart-area">
          @foreach([30,55,40,70,45,85,60,75,50,90,65,80,55,70,45,60,80,50,65,75,40,55,70,85,60,45,75,90,55,70] as $h)
          <div class="chart-bar {{ $h===90?'highlight':'' }}" style="height:{{ $h }}%"></div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Top products --}}
    <div class="card">
      <div class="card-head"><div><h2>Top Products</h2><p>Best performing items</p></div></div>
      <div class="card-pad" style="padding:0">
        <table class="tbl">
          <thead><tr><th>#</th><th>Product</th><th>Units Sold</th><th>Revenue</th><th>Growth</th></tr></thead>
          <tbody>
            <tr>
              <td style="color:var(--muted);font-family:var(--font-mono)">1</td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="inv-img" style="width:32px;height:32px">@include('seller.partials.icon', ['name' => 'bag', 'size' => 16])</div>
                  Sample Product
                </div>
              </td>
              <td class="mono">0</td>
              <td class="mono">₱0.00</td>
              <td><span style="color:var(--success);font-size:12px;font-weight:650">@include('seller.partials.icon', ['name' => 'trending-up', 'size' => 12]) —</span></td>
            </tr>
            <tr><td colspan="5"><div class="empty" style="padding:24px 20px"><h3>No sales data yet</h3></div></td></tr>
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
        @foreach([['Gross Revenue','₱0.00',''],['Platform Commission','- ₱0.00','color:var(--danger)'],['Shipping Fees','- ₱0.00','color:var(--danger)'],['Voucher Discounts','- ₱0.00','color:var(--danger)'],['Net Profit','₱0.00','color:var(--success);font-weight:700;font-size:15px']] as [$label,$val,$style])
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
        @foreach(['Food & Drinks','Clothing','Beauty','Electronics','Home & Living','Hobbies'] as $cat)
        <div style="display:flex;align-items:center;gap:10px;font-size:12px">
          <span style="flex:1;color:var(--text)">{{ $cat }}</span>
          <div style="width:100px;height:6px;background:var(--border);border-radius:3px;overflow:hidden">
            <div style="height:100%;background:var(--pink-line);width:0%;border-radius:3px"></div>
          </div>
          <span class="mono" style="width:40px;text-align:right;color:var(--muted)">0%</span>
        </div>
        @endforeach
      </div>
    </div>

    {{-- Performance metrics --}}
    <div class="card">
      <div class="card-head"><h2>Performance</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        @foreach([['Order Fulfillment Rate','—%'],['On-Time Delivery','—%'],['Return Rate','—%'],['Customer Satisfaction','—']] as [$metric,$val])
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
