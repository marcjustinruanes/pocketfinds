<?php $__env->startSection('title', 'Commission'); ?>
<?php $__env->startSection('page-title', 'Commission'); ?>
<?php $__env->startSection('page-sub', 'Platform commission ledger'); ?>

<?php $__env->startSection('content'); ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-pad">
    <div class="ledger-hero">
      <div>
        <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px">Total Commission Collected</div>
        <div class="ledger-num">₱<?php echo e(number_format($totalAmount, 2)); ?> <small>PHP</small></div>
      </div>
    </div>
  </div>
</div>

<div class="kpi-grid" style="margin-bottom:18px">
  <div class="kpi"><div class="label">Total Records</div><div class="value"><?php echo e($commissions->count()); ?></div></div>
  <div class="kpi"><div class="label">Total Commission</div><div class="value">₱<?php echo e(number_format($totalAmount, 2)); ?></div></div>
  <div class="kpi"><div class="label">Active Sellers</div><div class="value"><?php echo e($sellers); ?></div></div>
  <div class="kpi"><div class="label">Avg Rate</div><div class="value"><?php echo e($commissions->avg('commission_rate') ? number_format($commissions->avg('commission_rate'), 1).'%' : '—'); ?></div></div>
</div>

<div class="card">
  <div class="card-head"><h2>Commission Ledger</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Seller</th><th>Order ID</th><th>Sale Amount</th><th>Rate</th><th>Commission</th><th>Seller Earnings</th><th>Date</th></tr></thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $commissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr class="rail-row rail-approved">
          <td>
            <?php if($c->seller): ?>
            <div class="cell-user">
              <div class="avatar-sm"><?php echo e(strtoupper(substr($c->seller->first_name,0,1).substr($c->seller->last_name,0,1))); ?></div>
              <div><strong><?php echo e($c->seller->first_name); ?> <?php echo e($c->seller->last_name); ?></strong></div>
            </div>
            <?php else: ?>
            <span style="color:var(--muted)">Unknown</span>
            <?php endif; ?>
          </td>
          <td class="mono"><?php echo e(strtoupper(substr($c->order_id ?? $c->id, 0, 8))); ?></td>
          <td class="mono">₱<?php echo e(number_format($c->order_amount, 2)); ?></td>
          <td class="mono"><?php echo e($c->commission_rate); ?>%</td>
          <td class="mono">₱<?php echo e(number_format($c->commission_amount, 2)); ?></td>
          <td class="mono">₱<?php echo e(number_format($c->seller_earnings, 2)); ?></td>
          <td class="mono"><?php echo e($c->created_at?->format('Y-m-d')); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="7"><div class="empty"><div class="ic">₱</div><h3>No commission records yet</h3></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\commission.blade.php ENDPATH**/ ?>