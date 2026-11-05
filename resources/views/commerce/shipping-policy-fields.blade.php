@if(\App\Services\DeliveryGuarantee::regionalPoliciesInstalled())
<label class="form-row">Shipping offer for this region
  <select class="select" name="free_shipping"><option value="0">Buyer pays shipping</option><option value="1">Free shipping in this region</option></select>
</label>
<div class="form-grid-2">
  <label class="form-row">Earliest delivery (days)<input type="number" name="delivery_days_min" min="1" max="60" value="1" required></label>
  <label class="form-row">Latest delivery (days)<input type="number" name="delivery_days_max" min="1" max="60" value="7" required></label>
</div>
@endif

@if(\App\Services\DeliveryGuarantee::sellerVouchersInstalled())
<label class="form-row">Late-delivery voucher for this region<select class="select" name="late_voucher_enabled"><option value="0">No late-delivery voucher</option><option value="1">Offer a voucher for my shop</option></select></label>
<label class="form-row">Voucher amount (PHP)<input type="number" name="late_voucher_amount" min="0.01" max="1000000" step="0.01" value="40" required></label>
@endif
