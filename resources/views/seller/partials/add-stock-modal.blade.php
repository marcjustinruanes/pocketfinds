@php
  $hasVariations = !empty($product->variations);
  $needsPairStock = \App\Support\ProductVariationSet::combination($product->variations)['requires_stock_setup'] ?? false;
@endphp
<div class="modal-overlay" id="addStockModal-{{ $product->id }}">
  <div class="modal" style="max-width:440px;width:100%">
    <div class="modal-head">
      <div><h3>{{ $needsPairStock ? 'Set Combination Stock' : 'Add Stock' }}</h3><p>{{ $product->name }}</p></div>
      <button class="modal-close" data-modal-close>@include('seller.partials.icon',['name'=>'x','size'=>14])</button>
    </div>
    <form method="POST" action="{{ route('seller.inventory.add-stock', $product) }}">
      @csrf
      <div class="modal-body" style="max-height:70vh;overflow-y:auto;display:flex;flex-direction:column;gap:14px">

        <div style="background:var(--success-soft);border:1px solid var(--success-line);border-radius:9px;padding:10px 14px;font-size:12px;color:var(--success);display:flex;align-items:flex-start;gap:8px">
          @include('seller.partials.icon',['name'=>'check-circle','size'=>14])
          <span>@if($needsPairStock)Enter the confirmed stock for every variation combination. Use 0 for unavailable pairs.@else This product is already approved. Adding stock updates it immediately.@endif</span>
        </div>

        @if(!$hasVariations)
          <div class="form-row" style="margin:0">
            <label>Current Stock</label>
            <div class="field-value mono" style="font-size:18px;font-weight:700">{{ $product->total_stock }}</div>
          </div>
          <div class="form-row" style="margin:0">
            <label>Quantity to Add</label>
            <input type="number" name="qty" min="1" step="1" required placeholder="e.g. 10">
          </div>
        @else
          @foreach($product->variations as $vi => $variation)
            @foreach($variation['options'] ?? [] as $oi => $option)
              <div class="form-row" style="margin:0">
                <label>{{ $option['value'] }} @unless($needsPairStock)<span style="color:var(--muted);font-weight:400">(current stock: {{ (int) ($option['stock'] ?? 0) }})</span>@endunless</label>
                <input type="hidden" name="additions[{{ $vi }}_{{ $oi }}][group]" value="{{ $variation['name'] }}">
                <input type="hidden" name="additions[{{ $vi }}_{{ $oi }}][value]" value="{{ $option['value'] }}">
                <input type="number" name="additions[{{ $vi }}_{{ $oi }}][qty]" min="0" step="1" value="{{ $needsPairStock ? '' : '0' }}" placeholder="{{ $needsPairStock ? 'Stock for this combination' : 'Quantity to add' }}" @required($needsPairStock)>
              </div>
            @endforeach
          @endforeach
        @endif

      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit">
          @include('seller.partials.icon',['name'=>'plus','size'=>13]) {{ $needsPairStock ? 'Save Combination Stock' : 'Add Stock' }}
        </button>
      </div>
    </form>
  </div>
</div>
