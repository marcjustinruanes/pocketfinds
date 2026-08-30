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
    <p class="sub">Generated <?php echo e(now()->format('F j, Y g:i A')); ?></p>

    <table class="summary">
        <tr>
            <td><div class="label">Total Sales</div><div class="value">₱<?php echo e(number_format($totalAmount, 2)); ?></div></td>
            <td><div class="label">Total Commission</div><div class="value">₱<?php echo e(number_format($totalCommission, 2)); ?></div></td>
            <td><div class="label">Transactions</div><div class="value"><?php echo e(number_format($commissions->count())); ?></div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Order ID</th><th>Seller</th><th class="right">Sale Amount</th>
                <th class="right">Rate</th><th class="right">Commission</th>
                <th class="right">Seller Earnings</th><th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $commissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <td><?php echo e(strtoupper(substr($c->order_id ?? $c->id, 0, 8))); ?></td>
                <td><?php echo e($c->seller ? $c->seller->given_names.' '.$c->seller->last_name : 'Unknown'); ?></td>
                <td class="right">₱<?php echo e(number_format($c->order_amount, 2)); ?></td>
                <td class="right"><?php echo e($c->commission_rate); ?>%</td>
                <td class="right">₱<?php echo e(number_format($c->commission_amount, 2)); ?></td>
                <td class="right">₱<?php echo e(number_format($c->seller_earnings, 2)); ?></td>
                <td><?php echo e($c->created_at?->format('Y-m-d')); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="7">No transactions yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/reports-sales-pdf.blade.php ENDPATH**/ ?>