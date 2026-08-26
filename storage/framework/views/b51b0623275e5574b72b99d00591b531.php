<?php $__env->startSection('title', 'Confirm Delivery'); ?>
<?php $__env->startSection('page-title', 'Confirm Delivery'); ?>
<?php $__env->startSection('page-sub', 'Orders confirmed received by customers'); ?>

<?php $__env->startSection('content'); ?>
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr)">
  <div class="kpi"><div class="label">Delivered Today</div><div class="value">0</div><div class="delta up">Confirmed</div></div>
  <div class="kpi"><div class="label">This Week</div><div class="value">0</div><div class="delta up">Delivered</div></div>
  <div class="kpi"><div class="label">Pending Confirmation</div><div class="value">1</div><div class="delta">Awaiting buyer</div></div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Delivery Status</h2><p>You will be notified once the customer confirms receipt</p></div>
  </div>
  <div class="tabs" style="padding:0 20px;margin-bottom:0" data-tabs>
    <button class="tab active" data-tab="pending">Pending Confirmation</button>
    <button class="tab" data-tab="confirmed">Confirmed</button>
  </div>

  <div data-panel="pending">
    <table class="tbl">
      <thead><tr>
        <th>Order ID</th><th>Customer</th><th>Delivered On</th><th>Courier</th><th>Amount</th><th>Status</th>
      </tr></thead>
      <tbody>
        <tr>
          <td class="mono">#00001</td>
          <td>Sample Customer</td>
          <td style="font-size:12px;color:var(--muted)"><?php echo e(now()->format('M d, Y')); ?></td>
          <td>J&T Express</td>
          <td class="mono">₱299.00</td>
          <td><span class="stamp stamp-pending">Awaiting Buyer</span></td>
        </tr>
        <tr><td colspan="6"><div class="empty" style="padding:30px 20px"><h3>No more pending</h3></div></td></tr>
      </tbody>
    </table>
  </div>

  <div data-panel="confirmed" style="display:none">
    <div class="empty" style="padding:40px 20px">
      <div class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'check-circle', 'size' => 28], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
      <h3>No confirmed deliveries yet</h3>
      <p>Confirmed deliveries will appear here.</p>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\seller\deliveries.blade.php ENDPATH**/ ?>