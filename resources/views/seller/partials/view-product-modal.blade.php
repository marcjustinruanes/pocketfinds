@php
use Illuminate\Support\Facades\Storage;
$galleryImages = collect($product->images ?? [])->filter()->whenEmpty(fn ($images) => $product->image ? collect([$product->image]) : $images)->map(fn ($p) => Storage::url($p))->values();
$hasVariations = !empty($product->variations);
$hasDetails    = !empty($product->details);
$allOptions    = collect($product->variations ?? [])->flatMap(fn ($v) => $v['options'] ?? []);
$optionPrices  = $allOptions->pluck('price')->filter()->values();
$minPrice      = $optionPrices->push($product->price)->min();
$maxPrice      = $optionPrices->max();
$statusMeta = match($product->status) {
    'pending'  => ['stamp' => 'stamp-pending', 'icon' => 'clock', 'label' => 'Pending Review'],
    'active'   => ['stamp' => 'stamp-active', 'icon' => 'check-circle', 'label' => 'Active'],
    'rejected' => ['stamp' => 'stamp-rejected', 'icon' => 'x', 'label' => 'Rejected'],
    'archived' => ['stamp' => 'product-state is-archived', 'icon' => 'inventory', 'label' => 'Archived'],
    'draft' => ['stamp' => 'product-state is-draft', 'icon' => 'file', 'label' => 'Draft'],
    default    => ['stamp' => 'stamp-pending', 'icon' => 'tag', 'label' => ucfirst($product->status)],
};
@endphp
<div class="modal-overlay product-details-modal" id="viewProductModal-{{ $product->id }}">
  <div class="modal product-details-dialog" role="dialog" aria-modal="true" aria-labelledby="productDetailsTitle-{{ $product->id }}">
    <div class="modal-head product-details-header">
      <div style="min-width:0">
        <div class="product-details-heading-line"><span class="product-details-eyebrow">Product details</span><span class="stamp {{ $statusMeta['stamp'] }}">{{ $statusMeta['label'] }}</span></div>
        <h3 id="productDetailsTitle-{{ $product->id }}">{{ $product->name }}</h3>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex:none">
        <button type="button" class="btn btn-outline product-details-edit" title="Edit product" aria-label="Edit product"
          onclick="document.getElementById('viewProductModal-{{ $product->id }}').classList.remove('open');document.getElementById('editProductModal-{{ $product->id }}').classList.add('open')">
          @include('buyer.partials.icon',['name'=>'edit','size'=>16])
        </button>
        <button class="modal-close" data-modal-close title="Close" aria-label="Close">@include('seller.partials.icon',['name'=>'x','size'=>14])</button>
      </div>
    </div>

    <div class="modal-body product-details-body">

      {{-- Gallery + KPI stat cards --}}
      <div class="product-details-overview">
        <div class="product-details-gallery">
          <button type="button" class="product-details-main-image" aria-label="Enlarge product image" onclick="sellerOpenImgViewer('{{ $product->id }}')">
            @if($galleryImages->isNotEmpty())
              <img id="viewMainImg-{{ $product->id }}" src="{{ $galleryImages->first() }}" alt="{{ $product->name }}">
            @else
              <span style="color:var(--pink-line)">@include('seller.partials.icon',['name'=>'bag','size'=>40])</span>
            @endif
          </button>
          @if($galleryImages->count() > 1)
          <div class="product-gallery-thumbnails">
            @foreach($galleryImages as $i => $src)
            <button type="button" class="product-gallery-thumbnail {{ $i===0?'is-selected':'' }}" aria-label="Show product image {{ $i+1 }}" aria-pressed="{{ $i===0?'true':'false' }}" onclick="document.getElementById('viewMainImg-{{ $product->id }}').src=this.querySelector('img').src;this.parentElement.querySelectorAll('button').forEach(button=>{button.classList.remove('is-selected');button.setAttribute('aria-pressed','false')});this.classList.add('is-selected');this.setAttribute('aria-pressed','true')"><img src="{{ $src }}" alt="Product image {{ $i+1 }}"></button>
            @endforeach
          </div>
          @endif
          @if($product->video)
          <video src="{{ Storage::url($product->video) }}" controls class="product-details-video"></video>
          @endif
        </div>

        <dl class="product-facts-list">
          <div class="product-fact-row product-fact-price"><dt>Selling price</dt><dd>
            @if($product->discount_price && $product->discount_price < $product->price)
              <strong>&#8369;{{ number_format($product->discount_price,2) }}</strong><del>&#8369;{{ number_format($product->price,2) }}</del>
            @elseif($maxPrice > $minPrice)
              <strong>&#8369;{{ number_format($minPrice,2) }} &ndash; &#8369;{{ number_format($maxPrice,2) }}</strong>
            @else<strong>&#8369;{{ number_format($product->price,2) }}</strong>@endif
          </dd></div>
          <div class="product-fact-row"><dt>Available stock</dt><dd class="product-fact-stock"><span class="product-fact-stock-value">{{ (\App\Support\ProductVariationSet::combination($product->variations)['requires_stock_setup'] ?? false) ? 'Set combination stock' : number_format($product->total_stock) }}</span>
            @if(in_array($product->status,['active','archived'],true))
            <button type="button" class="btn btn-outline product-details-edit" onclick="document.getElementById('viewProductModal-{{ $product->id }}').classList.remove('open');document.getElementById('addStockModal-{{ $product->id }}').classList.add('open')">Add stock</button>
            @endif
          </dd></div>
          <div class="product-fact-row"><dt>SKU</dt><dd class="product-fact-sku">{{ $product->sku ?: 'Not assigned' }}</dd></div>
          <div class="product-fact-row"><dt>Weight</dt><dd>{{ number_format($product->weight_grams) }}@if($product->weight_grams_max) &ndash; {{ number_format($product->weight_grams_max) }}@endif g</dd></div>
          @if($product->width_cm && $product->height_cm)<div class="product-fact-row"><dt>Dimensions</dt><dd>@if($product->length_cm){{ $product->length_cm }} &times; @endif{{ $product->width_cm }} &times; {{ $product->height_cm }} cm <small>(@if($product->length_cm)L &times; @endif W &times; H)</small></dd></div>@endif
          <div class="product-fact-row"><dt>Condition</dt><dd>{{ ucfirst($product->condition ?? 'new') }}</dd></div>
        </dl>
      </div>

      {{-- Rejection reason --}}
      @if($product->status === 'rejected' && $product->rejection_note)
      <div style="background:var(--danger-soft);border:1px solid var(--danger-line);border-radius:9px;padding:12px 14px;font-size:13px;color:var(--danger);display:flex;gap:8px;align-items:flex-start">
        @include('seller.partials.icon',['name'=>'file','size'=>15])
        <span><strong>Reason from admin:</strong> {{ $product->rejection_note }}</span>
      </div>
      @endif

      @if($product->description)
      <section class="product-detail-section"><h4>Description</h4><p class="product-detail-description">{{ $product->description }}</p></section>
      @endif
      @if($hasDetails)
      <section class="product-detail-section"><h4>Specifications</h4><dl class="product-specification-list">
        @foreach($product->details as $row)<div><dt>{{ $row['label'] ?? '—' }}</dt><dd>{{ $row['value'] ?? '—' }}</dd></div>@endforeach
      </dl></section>
      @endif
      @if($hasVariations)
      <section class="product-variants-section" aria-label="Product variants">
       @foreach($product->variations as $group)
       <div class="product-variant-table-wrap"><table class="product-variant-table"><thead><tr><th>Variant</th><th>Price</th><th>Stock</th></tr></thead><tbody>
        @forelse(($group['options'] ?? []) as $opt)
        <tr><td><div class="product-variant-identity">@if(!empty($opt['image']))<img src="{{ Storage::url($opt['image']) }}" alt="{{ $opt['value'] ?? 'Variant' }}">@endif<span>{{ $opt['value'] ?? '—' }}</span></div></td><td>&#8369;{{ number_format($opt['price'] ?? $product->price,2) }}</td><td><span class="product-variant-stock {{ ($opt['stock'] ?? 0)<=0?'is-empty':'' }}">{{ number_format($opt['stock'] ?? 0) }}</span></td></tr>
        @empty<tr><td colspan="3">No options listed.</td></tr>@endforelse
       </tbody></table></div>
       @endforeach
      </section>
      @endif

    </div>

  </div>
</div>

<div id="sellerImgViewer-{{ $product->id }}" style="display:none;position:fixed;inset:0;z-index:400;background:rgba(0,0,0,.85);align-items:center;justify-content:center;cursor:zoom-out" onclick="sellerCloseImgViewer('{{ $product->id }}')">
  <img id="sellerImgViewerImg-{{ $product->id }}" src="" style="max-width:92vw;max-height:92vh;border-radius:10px;object-fit:contain;box-shadow:0 18px 60px rgba(0,0,0,.5);display:block">
  <button onclick="sellerCloseImgViewer('{{ $product->id }}')" style="position:absolute;top:18px;right:22px;width:38px;height:38px;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:rgba(0,0,0,.4);color:#fff;font-size:22px;line-height:1;cursor:pointer">×</button>
</div>
<script>
function sellerOpenImgViewer(id) {
  const img = document.getElementById('viewMainImg-' + id);
  if (!img) return;
  document.getElementById('sellerImgViewerImg-' + id).src = img.src;
  const v = document.getElementById('sellerImgViewer-' + id);
  v.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  const esc = e => { if (e.key === 'Escape') { sellerCloseImgViewer(id); document.removeEventListener('keydown', esc); } };
  document.addEventListener('keydown', esc);
}
function sellerCloseImgViewer(id) {
  document.getElementById('sellerImgViewer-' + id).style.display = 'none';
  document.body.style.overflow = '';
}
</script>
