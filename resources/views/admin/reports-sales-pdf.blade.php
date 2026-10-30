<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .sub { color: #6b7280; font-size: 11px; margin: 0 0 18px; }
        .summary { width: 100%; margin-bottom: 18px; border-collapse: collapse; }
        .summary td { padding: 8px 12px; border: 1px solid #e5e7eb; }
        .summary .label { color: #6b7280; font-size: 10px; text-transform: uppercase; }
        .summary .value { font-size: 14px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { padding: 6px 8px; border: 1px solid #e5e7eb; text-align: left; font-size: 10.5px; }
        table.data th { background: #f9fafb; text-transform: uppercase; font-size: 9px; color: #6b7280; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>PocketFinds — Sales Summary Report</h1>
    <p class="sub">Generated {{ now()->format('F j, Y g:i A') }}</p>

    <p>All completed orders. Order values include shipping and applied discounts; they are not profit or proof of payment. No platform commission is deducted.</p>
    @include('reports.payment-summary',['report'=>$paymentReport,'tableClass'=>'data'])
    <h2>Completed order sales</h2>
    <table class="data"><thead><tr><th>Order ID</th><th>Seller ID</th><th>Items PHP</th><th>Shipping PHP</th><th>Discount PHP</th><th>Buyer value PHP</th><th>Updated</th></tr></thead><tbody>
    @forelse($paymentReport['orders'] as $o)
    <tr><td>{{ $o->id }}</td><td>{{ $o->seller_id }}</td><td>{{ $o->subtotal }}</td><td>{{ $o->shipping_amount }}</td><td>{{ $o->discount_amount }}</td><td>{{ $o->total }}</td><td>{{ $o->updated_at?->format('Y-m-d') }}</td></tr>
    @empty<tr><td colspan="7">No completed orders.</td></tr>@endforelse
    </tbody></table>
</body></html>
