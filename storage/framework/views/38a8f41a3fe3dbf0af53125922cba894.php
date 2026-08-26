<?php $__env->startSection('title', 'Reports'); ?>
<?php $__env->startSection('page-title', 'Reports'); ?>
<?php $__env->startSection('page-sub', 'Financial, profit and sales performance reports'); ?>

<?php $__env->startSection('content'); ?>

<div class="card" style="margin-bottom:18px">
  <div class="card-pad" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <span style="font-size:13px;font-weight:650;color:var(--muted)">Date Range</span>
    <div class="form-row" style="margin:0;display:flex;align-items:center;gap:8px">
      <label style="font-size:12px;color:var(--muted);margin:0">From</label>
      <input type="date" value="<?php echo e(now()->startOfMonth()->format('Y-m-d')); ?>" style="border:1px solid var(--border);border-radius:9px;padding:8px 12px;font-size:13px;background:#fff">
    </div>
    <div class="form-row" style="margin:0;display:flex;align-items:center;gap:8px">
      <label style="font-size:12px;color:var(--muted);margin:0">To</label>
      <input type="date" value="<?php echo e(now()->format('Y-m-d')); ?>" style="border:1px solid var(--border);border-radius:9px;padding:8px 12px;font-size:13px;background:#fff">
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php $__currentLoopData = ['Today','This Week','This Month','Last Month','This Year']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $preset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <button class="btn btn-sm btn-outline <?php echo e($preset==='This Month'?'active':''); ?>" style="<?php echo e($preset==='This Month'?'border-color:var(--pink);color:var(--pink-dark)':''); ?>"><?php echo e($preset); ?></button>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <button class="btn btn-primary" style="margin-left:auto"><?php echo $__env->make('seller.partials.icon', ['name' => 'chart', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Generate</button>
    <button class="btn btn-outline"><?php echo $__env->make('seller.partials.icon', ['name' => 'download', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Export</button>
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
    
    <div class="card">
      <div class="card-head"><div><h2>Revenue Over Time</h2><p>Daily sales for selected period</p></div></div>
      <div class="card-pad">
        <div class="chart-area">
          <?php $__currentLoopData = [30,55,40,70,45,85,60,75,50,90,65,80,55,70,45,60,80,50,65,75,40,55,70,85,60,45,75,90,55,70]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="chart-bar <?php echo e($h===90?'highlight':''); ?>" style="height:<?php echo e($h); ?>%"></div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </div>

    
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
                  <div class="inv-img" style="width:32px;height:32px"><?php echo $__env->make('seller.partials.icon', ['name' => 'bag', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
                  Sample Product
                </div>
              </td>
              <td class="mono">0</td>
              <td class="mono">₱0.00</td>
              <td><span style="color:var(--success);font-size:12px;font-weight:650"><?php echo $__env->make('seller.partials.icon', ['name' => 'trending-up', 'size' => 12], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> —</span></td>
            </tr>
            <tr><td colspan="5"><div class="empty" style="padding:24px 20px"><h3>No sales data yet</h3></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="stack">
    
    <div class="card">
      <div class="card-head"><h2>Financial Summary</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        <?php $__currentLoopData = [['Gross Revenue','₱0.00',''],['Platform Commission','- ₱0.00','color:var(--danger)'],['Shipping Fees','- ₱0.00','color:var(--danger)'],['Voucher Discounts','- ₱0.00','color:var(--danger)'],['Net Profit','₱0.00','color:var(--success);font-weight:700;font-size:15px']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label,$val,$style]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;<?php echo e($label==='Net Profit'?'border-top:2px solid var(--border);margin-top:4px;padding-top:12px':''); ?>">
          <span style="color:var(--muted)"><?php echo e($label); ?></span>
          <span class="mono" style="<?php echo e($style); ?>"><?php echo e($val); ?></span>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head"><h2>Sales by Category</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <?php $__currentLoopData = ['Food & Drinks','Clothing','Beauty','Electronics','Home & Living','Hobbies']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="display:flex;align-items:center;gap:10px;font-size:12px">
          <span style="flex:1;color:var(--text)"><?php echo e($cat); ?></span>
          <div style="width:100px;height:6px;background:var(--border);border-radius:3px;overflow:hidden">
            <div style="height:100%;background:var(--pink-line);width:0%;border-radius:3px"></div>
          </div>
          <span class="mono" style="width:40px;text-align:right;color:var(--muted)">0%</span>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head"><h2>Performance</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        <?php $__currentLoopData = [['Order Fulfillment Rate','—%'],['On-Time Delivery','—%'],['Return Rate','—%'],['Customer Satisfaction','—']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$metric,$val]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="display:flex;justify-content:space-between;font-size:13px">
          <span style="color:var(--muted)"><?php echo e($metric); ?></span>
          <span class="mono" style="font-weight:650"><?php echo e($val); ?></span>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\seller\reports.blade.php ENDPATH**/ ?>