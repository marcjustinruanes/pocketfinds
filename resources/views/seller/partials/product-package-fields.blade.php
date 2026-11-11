<section class="product-editor-section product-editor-shipping product-package-section" aria-label="Package and condition">
 <h4>Package & condition</h4>
 <div class="product-package-inline">
  <div class="product-package-weight"><div class="product-weight-range"><input type="number" name="weight_grams" min="1" step="1" required placeholder="Min weight (g)" aria-label="Min weight (g)" value="{{ old('weight_grams', $packageProduct?->weight_grams) }}"><span aria-hidden="true">&ndash;</span><input type="number" name="weight_grams_max" min="1" step="1" placeholder="Max weight (g)" aria-label="Max weight (g)" value="{{ old('weight_grams_max', $packageProduct?->weight_grams_max) }}"></div></div>
  <label class="product-editor-floating is-select"><span class="product-editor-caption">Condition</span><select name="condition" required aria-label="Condition"><option value="new" @selected(old('condition', $packageProduct?->condition ?? 'new')==='new')>New</option><option value="used" @selected(old('condition', $packageProduct?->condition)==='used')>Used</option></select><fieldset class="product-editor-outline" aria-hidden="true"><legend><span>Condition</span></legend></fieldset></label>
   <label>Width (cm)<input type="number" name="width_cm" min="0" step="0.1" placeholder="Width" value="{{ old('width_cm', $packageProduct?->width_cm) }}"></label>
   <label>Height (cm)<input type="number" name="height_cm" min="0" step="0.1" placeholder="Height" value="{{ old('height_cm', $packageProduct?->height_cm) }}"></label>
 </div>
</section>
