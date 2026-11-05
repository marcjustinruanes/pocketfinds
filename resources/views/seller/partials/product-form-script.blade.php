<style>[data-role="product-form"] [hidden] { display:none !important; }</style>
<script src="{{ asset('js/variant-matrix.js') }}?v={{ filemtime(public_path('js/variant-matrix.js')) }}"></script>
<script>
// Shared Add/Edit product form logic. Called once per modal instance (the Add
// modal, and one Edit modal per product) — everything is scoped to `root` so
// multiple instances on the same page never share state.
window.initProductForm = function (root, opts) {
  opts = opts || {};
  const form = root.querySelector('[data-role="product-form"]');

  // ── Cover photos (existing server paths + newly picked files) ──────────
  const imgInput       = form.querySelector('[data-role="images-input"]');
  const imgThumbs      = form.querySelector('[data-role="img-thumbs"]');
  const imgPlaceholder = form.querySelector('[data-role="img-placeholder"]');
  const imgDropZone    = form.querySelector('[data-role="img-dropzone"]');
  const MAX_PHOTOS     = 9;
  let existingImages   = (opts.existingImages || []).slice(); // [{path, url}]
  let selectedImages   = []; // newly picked File objects

  imgDropZone.addEventListener('click', () => imgInput.click());
  imgInput.addEventListener('change', function () { addImages(this.files); this.value = ''; });
  ['dragover', 'dragleave', 'drop'].forEach(evt => imgDropZone.addEventListener(evt, e => {
    e.preventDefault();
    imgDropZone.style.borderColor = evt === 'dragover' ? 'var(--pink)' : 'var(--border)';
    if (evt === 'drop' && e.dataTransfer.files.length) addImages(e.dataTransfer.files);
  }));

  function totalPhotoCount() { return existingImages.length + selectedImages.length; }

  function addImages(fileList) {
    for (const file of fileList) {
      if (totalPhotoCount() >= MAX_PHOTOS) break;
      if (!file.type.startsWith('image/')) continue;
      selectedImages.push(file);
    }
    renderThumbs();
  }

  function makeThumbWrap(position, onRemove) {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:relative;width:64px;height:64px';
    wrap.innerHTML = `
      <img style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:2px solid ${position === 0 ? 'var(--pink)' : 'var(--border)'};background:var(--paper)">
      ${position === 0 ? '<span style="position:absolute;bottom:-6px;left:50%;transform:translateX(-50%);background:var(--pink-dark);color:#fff;font-size:9px;font-weight:700;border-radius:6px;padding:1px 5px;white-space:nowrap">Cover</span>' : ''}
      <button type="button" style="position:absolute;top:-6px;right:-6px;width:18px;height:18px;border-radius:50%;border:0;background:var(--danger);color:#fff;cursor:pointer;font-size:11px;line-height:1;display:flex;align-items:center;justify-content:center">&times;</button>`;
    wrap.querySelector('button').addEventListener('click', ev => { ev.stopPropagation(); onRemove(); });
    return wrap;
  }

  function renderThumbs() {
    imgThumbs.innerHTML = '';
    imgPlaceholder.style.display = totalPhotoCount() >= MAX_PHOTOS ? 'none' : 'flex';
    let position = 0;
    existingImages.forEach((img, index) => {
      const wrap = makeThumbWrap(position++, () => { existingImages.splice(index, 1); renderThumbs(); });
      wrap.querySelector('img').src = img.url;
      imgThumbs.appendChild(wrap);
    });
    selectedImages.forEach((file, index) => {
      const wrap = makeThumbWrap(position++, () => { selectedImages.splice(index, 1); renderThumbs(); });
      imgThumbs.appendChild(wrap);
      const r = new FileReader();
      r.onload = e => { wrap.querySelector('img').src = e.target.result; };
      r.readAsDataURL(file);
    });
  }
  if (existingImages.length) renderThumbs();

  function syncImages() {
    const dt = new DataTransfer();
    selectedImages.forEach(file => dt.items.add(file));
    imgInput.files = dt.files;
    form.querySelectorAll('.existing-image-input').forEach(el => el.remove());
    existingImages.forEach(img => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'existing_images[]';
      input.className = 'existing-image-input';
      input.value = img.path;
      form.appendChild(input);
    });
  }

  // ── Video (existing + replace/remove) ───────────────────────────────────
  const videoInput       = form.querySelector('[data-role="video-input"]');
  const videoDropZone    = form.querySelector('[data-role="video-dropzone"]');
  const videoPlaceholder = form.querySelector('[data-role="video-placeholder"]');
  const videoSelected    = form.querySelector('[data-role="video-selected"]');
  const videoFileName    = form.querySelector('[data-role="video-filename"]');
  const videoRemoveBtn   = form.querySelector('[data-role="video-remove-btn"]');
  const keepVideoInput   = form.querySelector('[data-role="keep-video-input"]');
  let hasVideo = !!opts.existingVideoName;

  if (hasVideo) {
    videoFileName.textContent = opts.existingVideoName;
    videoPlaceholder.style.display = 'none';
    videoSelected.style.display = 'block';
  }

  videoDropZone.addEventListener('click', () => videoInput.click());
  videoInput.addEventListener('change', function () {
    if (!this.files[0]) return;
    hasVideo = true;
    videoFileName.textContent = this.files[0].name;
    videoPlaceholder.style.display = 'none';
    videoSelected.style.display = 'block';
  });
  videoRemoveBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    hasVideo = false;
    videoInput.value = '';
    if (keepVideoInput) keepVideoInput.value = '';
    videoPlaceholder.style.display = 'flex';
    videoSelected.style.display = 'none';
  });

  // ── Variations ───────────────────────────────────────────────────────────
  const variationsList   = form.querySelector('[data-role="variations-list"]');
  const noVariationStock = form.querySelector('[data-role="no-variation-stock"]');
  const addVariationBtn  = form.querySelector('[data-role="add-variation-btn"]');
  const variationsJson   = form.querySelector('[data-role="variations-json"]');
  const existingCombination = (opts.existingVariations || []).find(group => group.type === 'combinations');
  const combinationStock = new Map((existingCombination?.options || []).map(option => [ProductVariantMatrix.key(option.attributes), {stock: existingCombination.requires_stock_setup ? '' : String(option.stock), price: option.price ?? ''}]));
  const combinationPanel = document.createElement('div');
  combinationPanel.hidden = true;
  combinationPanel.style.cssText = 'border:1px solid var(--border);border-radius:10px;padding:12px;margin-top:12px';
  variationsList.after(combinationPanel);
  let combinationSignature = '';
  let combinationError = '';
  let initializingVariations = true;
  let varCount = 0;
  let optUidCounter = 0;
  const optionImages = new Map(); // uid -> File (newly picked option photos)

  const colorPalette = @json(\App\Support\ProductColor::PALETTE);
  function namedColor(value) { const key = Object.keys(colorPalette).find(name => name.toLowerCase() === value.trim().toLowerCase()); return key ? colorPalette[key] : null; }
  const sizePresets = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', 'One Size'];
  function variationType(name) {
    return /^colou?rs?$/i.test(name || '') ? 'color' : /^sizes?$/i.test(name || '') ? 'size' : /^(grams?|weight)$/i.test(name || '') ? 'weight' : 'custom';
  }
  function configureVariation(div) {
    const type = variationType(div.querySelector('.var-name-input').value.trim());
    div.querySelector('.size-presets').hidden = type !== 'size';
    div.querySelector('.color-presets').hidden = type !== 'color';
    div.querySelector('.color-help').hidden = type !== 'color';
    div.querySelectorAll('.opt-color').forEach(input => input.hidden = type !== 'color');
  }
  function addVariationRow(prefillName, prefillOptions) {
    noVariationStock.style.display = 'none';
    const stockInput = form.querySelector('[data-role="stock-input"]');
    if (stockInput) stockInput.value = 0;
    const idx = varCount++;
    const div = document.createElement('div');
    div.dataset.varIdx = idx;
    div.style.cssText = 'background:var(--paper);border:1px solid var(--border);border-radius:10px;padding:12px 14px';
    div.innerHTML = `
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
        <select class="var-type" aria-label="Variation type"><option value="custom">Custom</option><option value="color">Color</option><option value="size">Size</option><option value="weight">Grams / Weight</option></select>
        <input type="text" placeholder="Variation name (e.g. Size, Color)" class="var-name-input" value="${prefillName ? prefillName.replace(/"/g,'&quot;') : ''}"
          style="flex:1;border:1px solid var(--border);border-radius:7px;padding:7px 10px;font-size:13px">
        <button type="button" class="var-remove-btn" style="border:0;background:none;cursor:pointer;color:var(--danger);padding:4px">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="size-presets" hidden style="margin-bottom:10px"></div>
      <div class="color-presets" hidden style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px"></div>
      <p class="color-help" hidden style="font-size:12px;color:var(--muted)">Choose a color from the palette or upload an option photo. Add a color name. With one variation, set stock per option. With two or more, set stock per combination below.</p>
      <div class="options-list" style="display:flex;flex-direction:column;gap:6px"></div>
      <button type="button" class="btn btn-sm btn-outline add-option-btn" style="margin-top:8px;font-size:11px;display:inline-flex;align-items:center;gap:4px">
        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Option
      </button>`;
    variationsList.appendChild(div);
    const typeSelect = div.querySelector('.var-type');
    typeSelect.value = variationType(prefillName);
    typeSelect.addEventListener('change', () => {
      div.querySelector('.var-name-input').value = {color:'Color', size:'Size', weight:'Grams', custom:''}[typeSelect.value];
      configureVariation(div); syncVariations();
    });
    div.querySelector('.var-name-input').addEventListener('input', () => {
      typeSelect.value = variationType(div.querySelector('.var-name-input').value.trim());
      configureVariation(div); syncVariations();
    });
    sizePresets.forEach(size => {
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'btn btn-sm btn-outline';
      button.textContent = size; button.style.margin = '0 4px 4px 0';
      button.addEventListener('click', () => {
        const rows = [...div.querySelectorAll('.options-list > div')];
        if (rows.some(row => row.querySelector('.opt-value').value.trim().toLowerCase() === size.toLowerCase())) return;
        const empty = rows.find(row => !row.querySelector('.opt-value').value.trim());
        if (empty) { empty.querySelector('.opt-value').value = size; syncVariations(); }
        else addOptionRow(div, {value:size});
      });
      div.querySelector('.size-presets').appendChild(button);
    });
    Object.entries(colorPalette).filter(([name]) => name !== 'Grey').forEach(([name, hex]) => {
      const button = document.createElement('button');
      button.type = 'button'; button.title = name; button.setAttribute('aria-label', 'Add ' + name);
      button.style.cssText = 'width:28px;height:28px;border-radius:50%;border:1px solid #aaa;background:' + hex;
      button.addEventListener('click', () => {
        const rows = [...div.querySelectorAll('.options-list > div')];
        if (rows.some(row => row.querySelector('.opt-value').value.trim().toLowerCase() === name.toLowerCase())) return;
        const empty = rows.find(row => !row.querySelector('.opt-value').value.trim());
        if (empty) {
          empty.querySelector('.opt-value').value = name; empty.querySelector('.opt-color').value = hex; empty.dataset.color = hex; syncVariations();
        } else addOptionRow(div, {value:name, color:hex});
      });
      div.querySelector('.color-presets').append(button);
    });
    div.querySelector('.var-remove-btn').addEventListener('click', () => {
      div.querySelectorAll('[data-opt-uid]').forEach(row => optionImages.delete(row.dataset.optUid));
      div.remove();
      if (variationsList.children.length === 0) noVariationStock.style.display = '';
      syncVariations();
    });
    div.querySelector('.add-option-btn').addEventListener('click', () => addOptionRow(div));

    if (prefillOptions && prefillOptions.length) {
      prefillOptions.forEach(opt => addOptionRow(div, opt));
    } else {
      addOptionRow(div);
    }
    configureVariation(div);
  }
  addVariationBtn.addEventListener('click', () => addVariationRow());

  function addOptionRow(varDiv, prefill) {
    prefill = prefill || {};
    const optList = varDiv.querySelector('.options-list');
    const row = document.createElement('div');
    const uid = 'opt' + (optUidCounter++);
    row.dataset.optUid = uid;
    row.style.cssText = 'display:flex;align-items:center;gap:8px;flex-wrap:wrap';
    row.innerHTML = `
      <div class="opt-photo-wrap" style="position:relative;width:44px;height:44px;flex-shrink:0"></div>
      <input type="file" class="opt-photo-input" accept="image/jpeg,image/png,image/webp" style="display:none">
      <input type="color" class="opt-color" aria-label="Option color" title="Choose color" style="width:40px;height:40px;padding:2px" hidden>
      <input type="text" placeholder="Option (e.g. Red, XL)" class="opt-value" value="${(prefill.value || '').replace(/"/g,'&quot;')}"
        style="flex:1;border:1px solid var(--border);border-radius:7px;padding:6px 10px;font-size:12px">
      <input type="number" placeholder="Price (₱)" min="0" step="0.01" class="opt-price" title="Leave blank to use the product's base price" value="${prefill.price ?? ''}"
        style="width:90px;border:1px solid var(--border);border-radius:7px;padding:6px 10px;font-size:12px">
      <input type="number" placeholder="Stock" min="0" class="opt-stock" value="${prefill.stock ?? 0}"
        style="width:80px;border:1px solid var(--border);border-radius:7px;padding:6px 10px;font-size:12px">
      <button type="button" class="opt-remove-btn" style="border:0;background:none;cursor:pointer;color:var(--muted);padding:2px">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>`;
    optList.appendChild(row);
    const colorInput = row.querySelector('.opt-color');
    colorInput.value = /^#[0-9a-f]{6}$/i.test(prefill.color || '') ? prefill.color : (namedColor(prefill.value || '') || '#000000');
    if (prefill.color || namedColor(prefill.value || '')) row.dataset.color = colorInput.value;
    row.querySelector('.opt-value').addEventListener('input', () => { const color = namedColor(row.querySelector('.opt-value').value); if (color && !row.dataset.customColor) { colorInput.value = color; row.dataset.color = color; } });
    colorInput.addEventListener('input', () => { row.dataset.color = colorInput.value; row.dataset.customColor = 'true'; syncVariations(); });
    configureVariation(varDiv);
    row.querySelectorAll('.opt-value, .opt-price, .opt-stock').forEach(el => el.addEventListener('input', syncVariations));
    row.querySelector('.opt-remove-btn').addEventListener('click', () => { row.remove(); optionImages.delete(uid); syncVariations(); });

    const photoWrap  = row.querySelector('.opt-photo-wrap');
    const photoInput = row.querySelector('.opt-photo-input');
    if (prefill.image_url) {
      row.dataset.existingImage = prefill.image_path;
      renderOptionPhoto(photoWrap, photoInput, uid, prefill.image_url, row);
    } else {
      resetOptionPhotoBtn(photoWrap, photoInput);
    }
    photoInput.addEventListener('change', function () {
      if (!this.files[0]) return;
      optionImages.set(uid, this.files[0]);
      delete row.dataset.existingImage;
      const r = new FileReader();
      r.onload = e => renderOptionPhoto(photoWrap, photoInput, uid, e.target.result, row);
      r.readAsDataURL(this.files[0]);
      syncVariations();
    });
    syncVariations();
  }

  function renderOptionPhoto(photoWrap, photoInput, uid, dataUrl, row) {
    photoWrap.innerHTML = `
      <div class="opt-photo-btn" style="width:44px;height:44px;border:1px solid var(--border);border-radius:7px;cursor:pointer;overflow:hidden;background:#fff">
        <img src="${dataUrl}" style="width:100%;height:100%;object-fit:cover;display:block">
      </div>
      <button type="button" class="opt-photo-enlarge" title="Enlarge" style="position:absolute;bottom:-6px;left:-6px;width:18px;height:18px;border-radius:50%;border:0;background:var(--text);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0">
        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
      </button>
      <button type="button" class="opt-photo-remove" title="Remove photo" style="position:absolute;top:-6px;right:-6px;width:18px;height:18px;border-radius:50%;border:0;background:var(--danger);color:#fff;cursor:pointer;font-size:11px;line-height:1;display:flex;align-items:center;justify-content:center;padding:0">&times;</button>`;
    photoWrap.querySelector('.opt-photo-btn').addEventListener('click', () => photoInput.click());
    photoWrap.querySelector('.opt-photo-enlarge').addEventListener('click', (ev) => { ev.stopPropagation(); openImageLightbox(dataUrl); });
    photoWrap.querySelector('.opt-photo-remove').addEventListener('click', (ev) => {
      ev.stopPropagation();
      optionImages.delete(uid);
      if (row) delete row.dataset.existingImage;
      photoInput.value = '';
      resetOptionPhotoBtn(photoWrap, photoInput);
      syncVariations();
    });
  }

  function resetOptionPhotoBtn(photoWrap, photoInput) {
    photoWrap.innerHTML = `
      <div class="opt-photo-btn" style="width:44px;height:44px;border:1px dashed var(--border);border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fff" title="Add a photo for this option">
        @include('seller.partials.icon',['name'=>'image','size'=>16])
      </div>`;
    photoWrap.querySelector('.opt-photo-btn').addEventListener('click', () => photoInput.click());
  }

  function syncVariations() {
    const vars = [];
    variationsList.querySelectorAll('[data-var-idx]').forEach(varDiv => {
      const name = varDiv.querySelector('.var-name-input').value.trim();
      const options = [];
      varDiv.querySelectorAll('.options-list > div').forEach(row => {
        const val      = row.querySelector('.opt-value').value.trim();
        const stock    = parseInt(row.querySelector('.opt-stock').value) || 0;
        const priceRaw = row.querySelector('.opt-price').value.trim();
        if (!val) return;
        const option = { value: val, stock };
        if (variationType(name) === 'color' && row.dataset.color) option.color = row.dataset.color;
        if (priceRaw !== '') option.price = parseFloat(priceRaw);
        if (optionImages.has(row.dataset.optUid)) option.image_key = row.dataset.optUid;
        else if (row.dataset.existingImage) option.existing_image = row.dataset.existingImage;
        options.push(option);
      });
      if (name) vars.push({ name, options });
    });
    if (initializingVariations) return;
    const combined = ProductVariantMatrix.usesCombinations(vars);
    variationsList.querySelectorAll('.opt-stock, .opt-price').forEach(input => { input.hidden = combined; input.disabled = combined; });
    combinationPanel.hidden = !combined;
    combinationPanel.querySelectorAll('input').forEach(input => input.disabled = !combined);
    if (!combined) {
      combinationSignature = ''; combinationError = '';
      variationsJson.value = vars.length ? JSON.stringify(vars) : '';
      return;
    }
    const axes = vars.map(group => ({name: group.name, options: group.options.map(option => {
      const {stock, price, ...axisOption} = option;
      return axisOption;
    })}));
    let rows = [];
    combinationError = '';
    try {
      if (axes.length > 4) throw new Error('Use up to four variation groups per product.');
      if (new Set(axes.map(axis => axis.name.toLowerCase())).size !== axes.length) throw new Error('Give each variation a different name.');
      if (axes.some(axis => new Set(axis.options.map(option => option.value.toLowerCase())).size !== axis.options.length)) throw new Error('Give each option a different name.');
      rows = ProductVariantMatrix.rows(axes);
      if (!rows.length) throw new Error('Add at least one option to each variation.');
      if (rows.some(row => row.value.length > 150) || new Set(rows.map(row => row.value)).size !== rows.length) throw new Error('Use shorter, distinct option names for combinations.');
    } catch (error) { combinationError = error.message; }
    const signature = JSON.stringify(axes.map(axis => [axis.name, axis.options.map(option => option.value)])) + combinationError;
    if (signature !== combinationSignature) {
      combinationSignature = signature;
      combinationPanel.replaceChildren();
      const heading = document.createElement('strong'); heading.textContent = 'Stock per combination';
      const hint = document.createElement('p');
      hint.style.cssText = 'font-size:12px;color:var(--muted);margin:6px 0 12px';
      hint.textContent = combinationError || 'Enter stock for each combination (0 if unavailable). Leave the price blank to use the product price. Existing separate option stock is not assigned automatically.';
      combinationPanel.append(heading, hint);
      rows.forEach(row => {
        const saved = combinationStock.get(row.key) || {stock:'', price:''};
        combinationStock.set(row.key, saved);
        const line = document.createElement('div');
        line.style.cssText = 'display:grid;grid-template-columns:minmax(80px,1fr) 80px 100px;align-items:center;gap:8px;margin-top:8px';
        const label = document.createElement('span'); label.textContent = row.value; label.style.fontSize = '12px';
        line.append(label);
        for (const field of ['stock', 'price']) {
          const input = document.createElement('input');
          input.type = 'number'; input.min = '0'; input.step = field === 'stock' ? '1' : '0.01';
          input.placeholder = field === 'stock' ? 'Stock' : 'Price';
          input.required = field === 'stock'; input.value = saved[field];
          input.setAttribute('aria-label', row.value + ' ' + field);
          input.style.cssText = 'width:100%;min-width:0;border:1px solid var(--border);border-radius:6px;padding:6px;font-size:12px';
          input.addEventListener('input', () => { saved[field] = input.value; syncVariations(); });
          line.append(input);
        }
        combinationPanel.append(line);
      });
    }
    const options = rows.map(row => {
      const saved = combinationStock.get(row.key);
      const option = {value:row.value, attributes:row.attributes, stock:saved?.stock === '' ? null : Number(saved?.stock)};
      if (saved?.price !== '' && saved?.price != null) option.price = Number(saved.price);
      return option;
    });
    variationsJson.value = JSON.stringify([{name: 'Options', type: 'combinations', axes, options}]);
  }

  function syncOptionImageInputs() {
    form.querySelectorAll('.opt-image-file-input').forEach(el => el.remove());
    optionImages.forEach((file, uid) => {
      const dt = new DataTransfer();
      dt.items.add(file);
      const input = document.createElement('input');
      input.type = 'file';
      input.name = `variation_images[${uid}]`;
      input.className = 'opt-image-file-input';
      input.style.display = 'none';
      input.files = dt.files;
      form.appendChild(input);
    });
  }

  const initialGroups = existingCombination ? existingCombination.axes : (opts.existingVariations || []);
  initialGroups.forEach(group => addVariationRow(group.name, group.options));
  initializingVariations = false;
  syncVariations();

  // ── Simple lightbox for enlarging option/cover photo previews (shared) ──
  function openImageLightbox(src) {
    let box = document.getElementById('productFormLightbox');
    if (!box) {
      box = document.createElement('div');
      box.id = 'productFormLightbox';
      box.style.cssText = 'position:fixed;inset:0;z-index:400;display:flex;align-items:center;justify-content:center;background:rgba(20,16,24,.82);padding:24px;cursor:zoom-out';
      box.innerHTML = `<img style="max-width:92vw;max-height:90vh;border-radius:10px;box-shadow:0 18px 60px rgba(0,0,0,.35)">
        <button type="button" style="position:absolute;top:18px;right:22px;width:36px;height:36px;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:rgba(0,0,0,.35);color:#fff;font-size:20px;line-height:1;cursor:pointer">&times;</button>`;
      box.addEventListener('click', () => box.remove());
      document.body.appendChild(box);
    }
    box.querySelector('img').src = src;
  }

  // ── Details ───────────────────────────────────────────────────────────
  const detailsList  = form.querySelector('[data-role="details-list"]');
  const addDetailBtn = form.querySelector('[data-role="add-detail-btn"]');
  const detailsJson  = form.querySelector('[data-role="details-json"]');

  function addDetailRow(prefill) {
    prefill = prefill || {};
    const row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:8px';
    row.innerHTML = `
      <input type="text" placeholder="Label (e.g. Material)" class="detail-label" value="${(prefill.label || '').replace(/"/g,'&quot;')}"
        style="flex:1;border:1px solid var(--border);border-radius:7px;padding:7px 10px;font-size:12px">
      <input type="text" placeholder="Value (e.g. Cotton)" class="detail-value" value="${(prefill.value || '').replace(/"/g,'&quot;')}"
        style="flex:1;border:1px solid var(--border);border-radius:7px;padding:7px 10px;font-size:12px">
      <button type="button" class="detail-remove-btn" style="border:0;background:none;cursor:pointer;color:var(--muted);padding:2px">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>`;
    detailsList.appendChild(row);
    row.querySelectorAll('.detail-label, .detail-value').forEach(el => el.addEventListener('input', syncDetails));
    row.querySelector('.detail-remove-btn').addEventListener('click', () => { row.remove(); syncDetails(); });
  }
  addDetailBtn.addEventListener('click', () => addDetailRow());

  function syncDetails() {
    const details = [];
    detailsList.querySelectorAll(':scope > div').forEach(row => {
      const label = row.querySelector('.detail-label').value.trim();
      const value = row.querySelector('.detail-value').value.trim();
      if (label && value) details.push({ label, value });
    });
    detailsJson.value = details.length ? JSON.stringify(details) : '';
  }

  if (opts.existingDetails && opts.existingDetails.length) {
    opts.existingDetails.forEach(d => addDetailRow(d));
  }

  // Sync everything right before submit
  form.addEventListener('submit', (e) => {
    syncVariations();
    if (combinationError || (!combinationPanel.hidden && !form.reportValidity())) {
      e.preventDefault();
      if (combinationError) alert(combinationError);
      return;
    }
    if (totalPhotoCount() === 0 && optionImages.size === 0 && !variationsList.querySelector('[data-existing-image]')) {
      e.preventDefault();
      alert('Add at least one product photo — either a cover photo or a photo on a variation option.');
      return;
    }
    syncImages();
    syncVariations();
    syncOptionImageInputs();
    syncDetails();
  });
};
</script>
