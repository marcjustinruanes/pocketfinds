<section class="cash-card" aria-label="Salary overview">
  <h2 class="salary-section-title"><span class="salary-section-icon"><x-admin-icon name="chart" /></span>Salary overview</h2>
  <div class="cash-stats">
    <div><span class="salary-metric-icon"><x-admin-icon name="clock" /></span><span>Outstanding obligations</span><strong>PHP {{ number_format($obligations->where('status','!=','paid')->sum('amount_cents')/100,2) }}</strong></div>
    <div><span class="salary-metric-icon"><x-admin-icon name="check-circle" /></span><span>Recorded paid</span><strong>PHP {{ number_format($obligations->where('status','paid')->sum('amount_cents')/100,2) }}</strong></div>
    <div><span class="salary-metric-icon"><x-admin-icon name="clock" /></span><span>Next unpaid due date</span><strong>{{ $obligations->where('status','!=','paid')->min('pay_date') ?? 'No generated obligations' }}</strong></div>
  </div>
  <p>Pending: awaiting preparation. Prepared: funds reserved. Verified: instruction reviewed, still unpaid. Paid: Admin recorded an external transfer. Due dates never automatically mark payments paid.</p>
</section>
