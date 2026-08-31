@php
use Illuminate\Support\Facades\Storage;
$galleryImages = collect($product->images ?? ($product->image ? [$product->image] : []))->map(fn ($p) => Storage::url($p))->values();
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
    default    => ['stamp' => 'stamp-pending', 'icon' => 'tag', 'label' => ucfirst($product->status)],
};
@endphp
<div class="modal-overlay" id="viewProductModal-{{ $product->id }}">
  <div class="modal" style="max-width:720px;width:100%">
    <div class="modal-head" style="background:var(--pink-soft);border-bottom-color:var(--pink-line)">
      <div style="min-width:0">
        <h3 style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $product->name }}</h3>
        <p style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span class="stamp {{ $statusMeta['stamp'] }}" style="display:inline-flex;align-items:center;gap:5px">
            @include('seller.partials.icon',['name'=>$statusMeta['icon'],'size'=>11]) {{ $statusMeta['label'] }}
          </span>
          <span>Submitted {{ $product->created_at->format('M d, Y') }}</span>
        </p>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex:none">
        <button type="button" class="modal-close" title="Edit product" aria-label="Edit product"
          onclick="document.getElementById('viewProductModal-{{ $product->id }}').classList.remove('open');document.getElementById('editProductModal-{{ $product->id }}').classList.add('open')">
          @include('seller.partials.icon',['name'=>'edit','size'=>14])
        </button>
        <button class="modal-close" data-modal-close title="Close" aria-label="Close">@include('seller.partials.icon',['name'=>'x','size'=>14])</button>
      </div>
    </div>

    <div class="modal-body" style="max-height:72vh;overflow-y:auto;display:flex;flex-direction:column;gap:20px">

      {{-- Gallery + KPI stat cards --}}
      <div style="display:grid;grid-template-columns:210px 1fr;gap:18px">
        <div>
          <div style="width:210px;height:210px;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--paper);box-shadow:var(--shadow-sm)">
            @if($galleryImages->isNotEmpty())
              <img id="viewMainImg-{{ $product->id }}" src="{{ $galleryImages->first() }}" style="width:100%;height:100%;object-fit:contain">
            @else
              <span style="color:var(--pink-line)">@include('seller.partials.icon',['name'=>'bag','size'=>40])</span>
            @endif
          </div>
          @if($galleryImages->count() > 1)
          <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
            @foreach($galleryImages as $i => $src)
            <img src="{{ $src }}" onclick="document.getElementById('viewMainImg-{{ $product->id }}').src=this.src"
              style="width:32px;height:32px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid {{ $i === 0 ? 'var(--pink)' : 'var(--border)' }}">
            @endforeach
          </div>
          @endif
          @if($product->video)
          <video src="{{ Storage::url($product->video) }}" controls style="width:210px;margin-top:8px;border-radius:8px"></video>
          @endif
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-content:start">
          <div>
            <div class="field-label">Price</div>
            <div class="field-value mono" style="font-size:19px;font-weight:700;color:var(--pink-dark)">
              @if($maxPrice > $minPrice)
                ₱{{ number_format($minPrice, 0) }}–{{ number_format($maxPrice, 0) }}
              @else
                ₱{{ number_format($product->price, 2) }}
              @endif
            </div>
          </div>
          <div>
            <div class="field-label">Stock</div>
            <div class="field-value mono" style="font-size:19px;font-weight:700;{{ $product->total_stock <= 0 ? 'color:var(--danger)' : '' }}">{{ $product->total_stock }}</div>
            @if($product->total_stock <= 0)<div style="font-size:11px;color:var(--danger)">Out of stock</div>@endif
          </div>
          <div><div class="field-label">Category</div><div class="field-value">{{ $product->category->name ?? '—' }}</div></div>
          <div><div class="field-label">SKU</div><div class="field-value mono">{{ $product->sku ?: '—' }}</div></div>
          <div style="grid-column:1/-1">
            <div class="field-label">Shipping</div>
            <div class="field-value">{{ $product->weight_grams }}@if($product->weight_grams_max)–{{ $product->weight_grams_max }}@endif g @if($product->length_cm) · {{ $product->length_cm }}×{{ $product->width_cm }}×{{ $product->height_cm }}cm @endif · {{ ucfirst($product->condition ?? 'new') }}</div>
          </div>
        </div>
      </div>

      {{-- Rejection reason --}}
      @if($product->status === 'rejected' && $product->rejection_note)
      <div style="background:var(--danger-soft);border:1px solid var(--danger-line);border-radius:9px;padding:12px 14px;font-size:13px;color:var(--danger);display:flex;gap:8px;align-items:flex-start">
        @include('seller.partials.icon',['name'=>'file','size'=>15])
        <span><strong>Reason from admin:</strong> {{ $product->rejection_note }}</span>
      </div>
      @endif

      {{-- Description --}}
      @if($product->description)
      <div>
        <div style="font-size:13px;font-weight:700;margin-bottom:6px;color:var(--pink-dark)">Description</div>
        <p style="font-size:13px;line-height:1.6;color:var(--text);white-space:pre-line;margin:0;background:var(--paper);border-radius:9px;padding:12px 14px">{{ $product->description }}</p>
      </div>
      @endif

      {{-- Specifications --}}
      @if($hasDetails)
      <div>
        <div style="font-size:13px;font-weight:700;margin-bottom:6px;color:var(--pink-dark)">Specifications</div>
        <div style="border:1px solid var(--border);border-radius:9px;overflow:hidden">
          @foreach($product->details as $row)
          <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:9px 14px;{{ !$loop->last ? 'border-bottom:1px solid var(--border)' : '' }};{{ $loop->even ? 'background:var(--paper)' : '' }}">
            <span style="color:var(--muted)">{{ $row['label'] ?? '—' }}</span>
            <span style="font-weight:600">{{ $row['value'] ?? '—' }}</span>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Variations --}}
      @if($hasVariations)
      <div>
        <div style="font-size:13px;font-weight:700;margin-bottom:8px;color:var(--pink-dark)">Variations</div>
        @foreach($product->variations as $group)
        <div style="margin-bottom:12px">
          <div style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">{{ $group['name'] ?? 'Option' }}</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            @forelse(($group['options'] ?? []) as $opt)
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid var(--border);border-radius:10px;font-size:12.5px;background:#fff">
              @if(!empty($opt['image']))
                <img src="{{ Storage::url($opt['image']) }}" style="width:32px;height:32px;object-fit:cover;border-radius:7px">
              @endif
              <div>
                <div style="font-weight:700">{{ $opt['value'] ?? '—' }}</div>
                <div style="display:flex;gap:6px;align-items:center;margin-top:1px">
                  @if(isset($opt['price']))<span class="mono" style="color:var(--pink-dark);font-weight:600">₱{{ number_format($opt['price'], 2) }}</span>@endif
                  <span class="stamp {{ ($opt['stock'] ?? 0) <= 0 ? 'stamp-rejected' : 'stamp-active' }}" style="padding:1px 6px 1px 5px;font-size:9px">{{ $opt['stock'] ?? 0 }} left</span>
                </div>
              </div>
            </div>
            @empty
            <span style="font-size:12px;color:var(--muted)">No options listed.</span>
            @endforelse
          </div>
        </div>
        @endforeach
      </div>
      @endif

    </div>

  </div>
</div>
