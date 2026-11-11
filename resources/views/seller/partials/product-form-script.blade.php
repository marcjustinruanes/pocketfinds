<style>[data-role="product-form"] [hidden] { display:none !important; }</style>
<script src="{{ asset('js/variant-matrix.js') }}?v={{ filemtime(public_path('js/variant-matrix.js')) }}"></script>
<script src="{{ asset('js/nested-product-variants.js') }}?v={{ filemtime(public_path('js/nested-product-variants.js')) }}"></script>
<script src="{{ asset('js/product-package-fields.js') }}?v={{ filemtime(public_path('js/product-package-fields.js')) }}"></script>
<script>
// Shared Add/Edit product form logic. Called once per modal instance (the Add
// modal, and one Edit modal per product) — everything is scoped to `root` so
// multiple instances on the same page never share state.
window.initProductForm = function (root, opts) {
  opts = opts || {};
  const form = root.querySelector('[data-role="product-form"]');
  form.noValidate=true;
  const formError=form.querySelector('[data-product-form-error]') || document.createElement('div');
  if(!formError.hasAttribute('data-product-form-error')){formError.dataset.productFormError='';formError.className='product-form-error';formError.setAttribute('role','alert');formError.hidden=true;(form.querySelector('.modal-body') || form).prepend(formError);}
  function showFormError(message,input){
    formError.textContent=message;formError.hidden=false;
    if(input&&input.getClientRects().length&&!input.disabled)input.focus({preventScroll:true});
    (input?.getClientRects().length?input:formError).scrollIntoView({block:'nearest',behavior:'smooth'});
  }
  const packageSection=form.querySelector('.product-package-section');
  const packageFields=new Map(['weight_grams','weight_grams_max','width_cm','height_cm','condition'].map(name=>[name,packageSection?.querySelector('[name="'+name+'"]')]).filter(([,input])=>input));
  let packageDetailsReady=false;
  const packageNote=document.createElement('p');packageNote.className='product-package-variation-note';packageNote.textContent='Fields set in your variations are filled automatically.';packageNote.hidden=true;packageSection?.append(packageNote);
  function syncPackageFields(variations){
    if(!packageSection)return;
    const derived=ProductPackageFields.fromVariations(variations);
    let automatic=false;
    packageFields.forEach((input,name)=>{
      let mirror=form.querySelector('[data-package-mirror="'+name+'"]');
      if(Object.hasOwn(derived,name)){
        automatic=true;
        if(!input.disabled){input.dataset.packageOriginal=input.value;input.dataset.packageRequired=String(input.required);}
        input.disabled=true;input.required=false;input.name='';input.value=derived[name]??'';
        if(!mirror){mirror=document.createElement('input');mirror.type='hidden';mirror.dataset.packageMirror=name;mirror.name=name;form.append(mirror);}mirror.value=derived[name]??'';
      }else if(input.disabled){
        input.disabled=false;input.name=name;input.required=input.dataset.packageRequired==='true';input.value=input.dataset.packageOriginal??'';mirror?.remove();
      }
    });
    const weightContainer=packageSection.querySelector('.product-package-weight');if(weightContainer)weightContainer.hidden=Object.hasOwn(derived,'weight_grams');
    ['width_cm','height_cm'].forEach(name=>{const label=packageFields.get(name)?.closest('label');if(label)label.hidden=Object.hasOwn(derived,name);});
    packageNote.hidden=!automatic;if(packageDetailsReady)renderAutomaticDetails();
  }

  // Use the same notched, transparent floating captions for static and added fields.
  function floatProductFields() {
    form.querySelectorAll('input[placeholder],textarea[placeholder],.product-child-row select').forEach(input => {
      if (input.type === 'hidden' || input.hidden || input.parentElement.matches('.product-floating-control,.product-floating-number,.product-option-floating-control,.product-editor-floating')) return;
      let label = input.closest('label');
      const sibling = input.previousElementSibling;
      let caption;
      if (label && label.contains(input)) {
        caption = [...label.children].find(el => el.tagName === 'SPAN');
        if (!caption) {
          const text = [...label.childNodes].filter(node => node.nodeType === Node.TEXT_NODE).map(node => node.textContent).join('').trim();
          if (text) {
            [...label.childNodes].filter(node => node.nodeType === Node.TEXT_NODE).forEach(node => node.remove());
            caption = document.createElement('span'); caption.textContent = text; label.prepend(caption);
          }
        }
      } else if (sibling?.tagName === 'LABEL' && !sibling.querySelector('input,select,textarea')) {
        label = document.createElement('label'); input.before(label); caption = document.createElement('span'); caption.textContent = sibling.textContent.trim(); sibling.remove(); label.append(input);
      } else {
        label = document.createElement('label'); input.before(label); label.append(input);
      }
      if (!caption) { caption = document.createElement('span'); caption.textContent = input.getAttribute('aria-label') || input.placeholder || 'Size'; }
      caption.className = 'product-editor-caption';
      const outline = document.createElement('fieldset'); outline.className = 'product-editor-outline'; outline.setAttribute('aria-hidden','true');
      const legend = document.createElement('legend'); const text = document.createElement('span'); text.textContent = caption.textContent; legend.append(text); outline.append(legend);
      label.classList.add('product-editor-floating'); label.append(caption,outline); input.placeholder = ' ';
      if (input.tagName === 'SELECT') label.classList.add('is-select');
    });
  }
  new MutationObserver(floatProductFields).observe(form,{childList:true,subtree:true});

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
  const childFields = ['Size', 'Weight', 'Dimensions', 'Color'];
  const existingEditorGroups=(opts.existingVariations || []).flatMap(group=>group.editor_groups || []);
  const existingCombination = (opts.existingVariations || []).find(group => group.type === 'combinations');
  const primaryAxis = existingCombination?.axes.find(axis=>axis.options.some(option=>option.variation_name)) || existingCombination?.axes.find(axis => /^colou?rs?$/i.test(axis.name)) || existingCombination?.axes[0];
  if (existingCombination) existingCombination.axes.filter(axis => axis !== primaryAxis).forEach(axis => { if (!childFields.includes(axis.name)) childFields.push(axis.name); });
  const combinationPanel = document.createElement('div');
  combinationPanel.hidden = true;
  combinationPanel.className = 'product-variation-error';
  combinationPanel.setAttribute('role','alert');
  combinationPanel.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 3v1"/></svg><span></span>';
  variationsList.after(combinationPanel);
  let combinationError = '';
  let initializingVariations = true;
  const groupTable=document.createElement('table');groupTable.className='product-variation-groups';groupTable.hidden=true;
    groupTable.innerHTML='<thead><tr><th>Image</th><th>Variation name</th><th>Price</th><th>Stock</th><th>Manage</th></tr></thead><tbody></tbody>';variationsList.after(groupTable);
  function refreshGroupTable(){
    const body=groupTable.querySelector('tbody');body.replaceChildren();
    variationsList.querySelectorAll('[data-var-idx][data-saved-group=true]').forEach(group=>{
      group.dataset.typeLocked='true';
      const tr=document.createElement('tr'),imageCell=document.createElement('td'),name=document.createElement('td'),options=document.createElement('small'),price=document.createElement('td'),stock=document.createElement('td'),actions=document.createElement('td');
      tr.dataset.variationName=variationNameInput(group).value;
      const imageBox=document.createElement('span');imageBox.className='product-group-table-thumbnail';
      if(group.dataset.groupImageUrl){const image=document.createElement('img');image.className='product-group-table-image';image.src=group.dataset.groupImageUrl;image.alt='';imageBox.append(image);}imageCell.append(imageBox);
      const title=document.createElement('strong');title.textContent=variationNameInput(group).value;name.append(title,options);
      options.textContent=[...group.querySelectorAll('.options-list>[data-opt-uid] .opt-value')].map(input=>input.value.trim()).filter(Boolean).join(', ') || 'No options yet';
      const savedRows=[...group.querySelectorAll('.options-list>[data-opt-uid]')].filter(row=>row.querySelector('.opt-value').value.trim());
      const priceValues=savedRows.flatMap(row=>row.dataset.optionType==='size'?row.readSizeVariants().map(option=>option.price??form.elements.namedItem('price')?.value??0):row.readChildren().length?row.readChildren().map(option=>option.price!==''&&option.price!=null?Number(option.price):Number(form.elements.namedItem('price')?.value || 0)):[Number(row.querySelector('.opt-price').value || form.elements.namedItem('price')?.value || 0)]);
      const money=value=>String.fromCharCode(8369)+Number(value).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
      price.textContent=priceValues.length?(Math.min(...priceValues)===Math.max(...priceValues)?money(priceValues[0]):money(Math.min(...priceValues))+' – '+money(Math.max(...priceValues))):'—';
      stock.textContent=savedRows.length?String(savedRows.reduce((total,row)=>total+(row.dataset.optionType==='size'?row.readSizeVariants().reduce((sum,item)=>sum+Number(item.stock||0),0):row.readChildren().length?row.readChildren().reduce((sum,item)=>sum+Number(item.stock||0),0):Number(row.querySelector('.opt-stock').value||0)),0)):'—';
      const edit=document.createElement('button');edit.type='button';edit.className='btn btn-outline product-group-edit';edit.title='Edit variation';edit.setAttribute('aria-label','Edit '+variationNameInput(group).value);edit.innerHTML='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 5 5-12 12-6 1 1-6L16 3Z"/><path d="m14 5 5 5"/></svg>';
      edit.addEventListener('click',()=>{
        variationsList.querySelectorAll('[data-var-idx]:not([data-saved-group=true])').forEach(active=>{
          if(active===group)return;
          if(!variationNameInput(active).value.trim()&&![...active.querySelectorAll('.opt-value')].some(input=>input.value.trim())){active.variationHeader?.remove();active.remove();}
          else {active.dataset.savedGroup='true';active.hidden=true;if(active.variationHeader)active.variationHeader.hidden=true;}
        });
        delete group.dataset.savedGroup;group.hidden=false;if(group.variationHeader)group.variationHeader.hidden=false;refreshGroupTable();syncVariations();
      });
      const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline product-group-remove';remove.innerHTML='&times;';remove.title='Remove variation';remove.setAttribute('aria-label','Remove '+variationNameInput(group).value);remove.addEventListener('click',()=>{optionImages.delete(group.dataset.groupImageKey);group.variationHeader?.remove();group.remove();refreshGroupTable();syncVariations();});
      actions.append(edit,remove);tr.append(imageCell,name,price,stock,actions);body.append(tr);
    });groupTable.hidden=!body.children.length;
  }

  let varCount = 0;
  let optUidCounter = 0;
  const optionImages = new Map(); // uid -> File (newly picked option photos)

  const colorPalette = @json(\App\Support\ProductColor::PALETTE);
  function namedColor(value) { const key = Object.keys(colorPalette).find(name => name.toLowerCase() === value.trim().toLowerCase()); return key ? colorPalette[key] : null; }
  const sizePresets = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', 'One Size'];
  function variationType(name) {
    return /^colou?rs?$/i.test(name || '') ? 'color' : /^sizes?$/i.test(name || '') ? 'size' : /^(grams?|weight)$/i.test(name || '') ? 'weight' : /^dimensions?$/i.test(name || '') ? 'dimensions' : 'custom';
  }
  function variationNameInput(div) { return div.querySelector('.var-name-input') || div.variationHeader?.querySelector('.var-name-input'); }
  function configureVariation(div) {
    const type = (div.variationHeader || div).querySelector('.var-type').value;
    div.querySelector('.size-presets').hidden = true;
    div.querySelector('.size-presets').open = type === 'size';
    ((div.variationHeader || div).querySelector('.color-presets') || div.querySelector('.color-presets')).hidden = type !== 'color';
    const nameRow = div.querySelector('.product-variant-name-row');
    const nameLabel = variationNameInput(div).closest('label');
    nameRow.hidden = type !== 'custom';
    nameRow.classList.toggle('has-variation-name', type === 'custom');
    div.querySelector('thead th:nth-child(2)').textContent={color:'Color',size:'Size',weight:'Weight',dimensions:'Dimensions',custom:'Option'}[type];
    nameLabel.hidden = type !== 'custom';
    div.querySelector('.add-option-btn').hidden = type !== 'custom';
    div.querySelector('.add-option-btn').textContent = 'Add option';
    div.querySelectorAll('.opt-value').forEach(input=>{
      const prompt=type==='color'?'Color name':type==='size'?'Size':type==='weight'?'Weight':'Option name';
      input.placeholder=' ';input.setAttribute('aria-label',prompt);
      const wrapper=input.closest('.product-option-floating-control');
      const caption=wrapper?.querySelector('.product-floating-caption');
      const legend=wrapper?.querySelector('.product-floating-outline legend span');
      if(caption)caption.textContent=prompt;
      if(legend)legend.textContent=prompt;
      if(wrapper)wrapper.classList.toggle('has-size-select',type==='size');
      if(caption)caption.hidden=['weight','dimensions'].includes(type);
    });
    div.querySelectorAll('.opt-color-code, .opt-color-swatch').forEach(code => code.hidden = type !== 'color');
    div.querySelectorAll('.options-list > [data-opt-uid]').forEach(row => {
      row.dataset.optionType=type;
      const customize=row.querySelector('.product-add-measurements');if(customize)customize.hidden=['size','weight','dimensions'].includes(type);
      const dimensions=row.querySelector('.product-size-dimensions-toggle');if(dimensions){dimensions.hidden=!['weight','dimensions'].includes(type);dimensions.textContent=type==='dimensions'?'Add weight':'Add dimensions';}
      row.querySelectorAll('.product-size-dimensions input').forEach((input,index)=>{
        const prompt=type==='dimensions'?(index?'Maximum weight (g)':'Minimum weight (g)'):(index?'Height (cm)':'Width (cm)');
        const label=input.closest('label'),caption=label.querySelector('.product-editor-caption') || label.querySelector('span');caption.textContent=prompt;
        const legend=label.querySelector('legend span');if(legend)legend.textContent=prompt;
        input.setAttribute('aria-label',prompt);input.required=!row.querySelector('.product-size-dimensions').hidden&&(type!=='dimensions'||index===0);
      });
      const original = row.querySelector('.opt-value');
      let sizeSelect = row.querySelector('.product-main-size-select');
      if (type === 'size') {
        if(!sizeSelect){
          sizeSelect=document.createElement('select');sizeSelect.className='product-main-size-select';sizeSelect.setAttribute('aria-label','Size');
          sizeSelect.addEventListener('change',()=>{original.value=sizeSelect.value;original.setCustomValidity('');sizeSelect.setCustomValidity('');syncVariations();});original.after(sizeSelect);
        }
        const choices=[...sizePresets];if(original.value&&!choices.includes(original.value))choices.push(original.value);
        sizeSelect.replaceChildren(new Option('Select size',''));choices.forEach(size=>sizeSelect.append(new Option(size,size)));sizeSelect.value=original.value;
        const inlineDimensions=row.querySelector('.product-size-dimensions');
        inlineDimensions.hidden=false;row.querySelector('.product-option-identity').append(inlineDimensions);
        const inputs=[...inlineDimensions.querySelectorAll('input')],hasDimensions=inputs.some(input=>input.value);
        inputs.forEach(input=>input.required=hasDimensions);
      }
      let weightFields=row.querySelector('.product-main-weight-fields');
      if(['weight','dimensions'].includes(type)&&!weightFields){
        weightFields=document.createElement('div');weightFields.className='product-main-weight-fields';
        const inputs=['Minimum weight (g)','Maximum weight (g)'].map((name,index)=>{
          const input=document.createElement('input');input.type='number';input.min='0.001';input.step='any';input.placeholder=name;input.dataset.mainMeasurement=index;
          input.addEventListener('keydown',event=>{if(!event.ctrlKey&&!event.metaKey&&['e','E','+','-'].includes(event.key))event.preventDefault();});
          input.addEventListener('input',()=>{
            const [first,second]=weightFields.querySelectorAll('input');
            if(type==='dimensions')original.value=first.value&&second.value?first.value+' x '+second.value+' cm':'';
            else original.value=first.value?first.value+(second.value?' - '+second.value:'')+' g':'';
            first.setCustomValidity(type==='weight'&&second.value&&Number(second.value)<Number(first.value)?'Maximum weight must be at least the minimum weight.':'');
            original.setCustomValidity('');syncVariations();
          });weightFields.append(input);return input;
        });
        if(type==='weight'){const separator=document.createElement('span');separator.className='product-weight-range-separator';separator.textContent='–';separator.setAttribute('aria-hidden','true');inputs[0].after(separator);}
        original.after(weightFields);
      }
      if(weightFields){
        weightFields.hidden=!['weight','dimensions'].includes(type);
        const values=original.value.match(/\d+(?:\.\d+)?/g)||[];
        weightFields.querySelectorAll('input').forEach((input,index)=>{
          input.disabled=weightFields.hidden;input.required=!!original.value&&(type==='dimensions'||type==='weight'&&index===0);
          input.placeholder=type==='dimensions'?(index?'Height (cm)':'Width (cm)'):(index?'Maximum weight (g)':'Minimum weight (g)');
          input.setAttribute('aria-label',input.placeholder);input.value=values[index]||'';
          const caption=input.closest('label')?.querySelector('.product-editor-caption');if(caption){caption.textContent=input.placeholder;input.placeholder=' ';const legend=input.closest('label').querySelector('legend span');if(legend)legend.textContent=caption.textContent;}
        });
      }
      original.hidden=['size','weight','dimensions'].includes(type);if(sizeSelect)sizeSelect.hidden=type!=='size';
      row.renderCompactSummary?.();
    });
    div.querySelectorAll('[data-child-field]').forEach(label => { label.hidden = label.dataset.enabled !== 'true' || (['size','weight'].includes(type)&&variationType(label.dataset.childField)===type) || label.dataset.childField.toLowerCase() === variationNameInput(div).value.trim().toLowerCase(); });
  }
  function addVariationRow(prefillName, prefillOptions) {
    noVariationStock.style.display = 'none';
    const stockInput = form.querySelector('[data-role="stock-input"]');
    if (stockInput) stockInput.value = 0;
    const idx = varCount++;
    const div = document.createElement('div');
    div.dataset.varIdx = idx;
    div.className = 'product-main-variation';
    div.innerHTML = `
      <div class="product-main-variation-header">
        <label class="product-option-type-label"><span>Option type</span><select class="var-type" aria-label="Variation type"><option value="custom">Custom</option><option value="color">Color</option><option value="size">Size</option><option value="weight">Weight</option><option value="dimensions">Dimensions</option></select></label>
        <input type="text" placeholder="Variation name (e.g. Size, Color)" class="var-name-input" value="${prefillName ? prefillName.replace(/"/g,'&quot;') : ''}"
          style="flex:1;border:1px solid var(--border);border-radius:7px;padding:7px 10px;font-size:13px">
        <button type="button" aria-label="Remove variation group" title="Remove variation group" class="var-remove-btn" style="border:0;background:none;cursor:pointer;color:var(--danger);padding:4px">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <details class="size-presets" hidden><summary>Standard sizes</summary><div class="size-preset-options"></div></details>

      <div class="product-option-table-wrap"><table class="product-option-table"><thead><tr><th>Image</th><th>Option</th><th>Price</th><th>Stock</th><th><span class="product-visually-hidden">Actions</span></th></tr></thead><tbody class="options-list"></tbody><tfoot><tr><td colspan="5"><div class="color-presets" hidden></div><button type="button" class="btn btn-sm btn-outline add-option-btn">Add option</button></td></tr></tfoot></table></div>`;
    variationsList.appendChild(div);
    div.querySelector('.product-option-table thead th:last-child').textContent='Manage';
    const optionHeading = variationsList.closest('.product-editor-variations')?.querySelector('.product-options-heading');
    div.variationHeader = div.querySelector('.product-main-variation-header');
    if (optionHeading) optionHeading.append(div.variationHeader);
    const nameRow=document.createElement('div');nameRow.className='product-variant-name-row';
    const groupImageKey='group'+idx;div.dataset.groupImageKey=groupImageKey;
    const groupImage=document.createElement('button');groupImage.type='button';groupImage.className='product-variation-image';groupImage.setAttribute('aria-label','Upload variation image');groupImage.title='Upload variation image';
    const groupFile=document.createElement('input');groupFile.type='file';groupFile.accept='image/jpeg,image/png,image/webp';groupFile.hidden=true;
    const emptyGroupIcon='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 6-6 4 4 3-3 5 5"/></svg>';
    groupImage.innerHTML=emptyGroupIcon;
    const showGroupImage=url=>{div.dataset.groupImageUrl=url;groupImage.replaceChildren();const image=document.createElement('img');image.src=url;image.alt='Variation image';groupImage.append(image);};
    const restoredGroup=existingEditorGroups.find(group=>group.name===prefillName);
    if(restoredGroup?.image){div.dataset.groupExistingImage=restoredGroup.image;showGroupImage(restoredGroup.image_url || '/storage/'+restoredGroup.image);}
    groupImage.addEventListener('click',()=>groupFile.click());
    groupFile.addEventListener('change',()=>{const file=groupFile.files[0];if(!file||!file.type.startsWith('image/'))return;optionImages.set(groupImageKey,file);delete div.dataset.groupExistingImage;const reader=new FileReader();reader.onload=()=>{showGroupImage(reader.result);refreshGroupTable();};reader.readAsDataURL(file);syncVariations();});
    nameRow.append(groupImage,groupFile);
    const nameLabel=document.createElement('label');nameLabel.className='product-floating-control product-variant-name-field';
    const nameCaption=document.createElement('span');nameCaption.className='product-floating-caption';nameCaption.textContent='Variation name';
    const nameInput=variationNameInput(div);nameInput.placeholder=' ';nameInput.title='Example: Material or Texture';nameInput.setAttribute('aria-label','Variation name');
    const nameOutline=document.createElement('fieldset');nameOutline.className='product-floating-outline';nameOutline.setAttribute('aria-hidden','true');nameOutline.innerHTML='<legend><span>Variation name</span></legend>';
    nameLabel.append(nameInput,nameCaption,nameOutline);nameRow.append(nameLabel,div.querySelector('.add-option-btn'));
    const saveName=document.createElement('button');saveName.type='button';saveName.className='btn btn-primary product-save-variation-name';saveName.textContent='Add';
    saveName.addEventListener('click',()=>{
      if(!nameInput.value.trim()){combinationPanel.querySelector('span').textContent='Enter a variation name before adding it.';combinationPanel.hidden=false;nameInput.focus();return;}
      const duplicate=[...variationsList.querySelectorAll('[data-var-idx]')].some(group=>group!==div&&variationNameInput(group).value.trim().toLowerCase()===nameInput.value.trim().toLowerCase());
      if(duplicate){combinationPanel.querySelector('span').textContent='Use a different variation name for each group.';combinationPanel.hidden=false;return;}
      const populated=[...div.querySelectorAll('.options-list>[data-opt-uid]')].filter(row=>row.querySelector('.opt-value').value.trim());
      for(const row of populated)if(row.dataset.committed!=='true'&&!row.commitVariant())return;
      syncVariations();if(combinationError)return;
      div.dataset.savedGroup='true';div.hidden=true;if(div.variationHeader)div.variationHeader.hidden=true;
      refreshGroupTable();addVariationRow('');syncVariations();
    });nameRow.append(saveName);
    div.querySelector('.product-option-table-wrap').before(nameRow);
    addVariationBtn.hidden = true;
    const typeSelect = (div.variationHeader || div).querySelector('.var-type');
    typeSelect.value = variationType(prefillName);
    let previousType = typeSelect.value;
    const typeDrafts = new Map();
    const otherSets=document.createElement('tbody');otherSets.className='product-other-option-sets';div.querySelector('.options-list').after(otherSets);
    function renderOtherSets(){
      otherSets.replaceChildren();
      typeDrafts.forEach((draft,kind)=>{
        if(kind===typeSelect.value)return;
        const rows=draft.rows.filter(row=>row.querySelector('.opt-value').value.trim());
        if(!rows.length)return;
        const heading=document.createElement('tr');heading.className='product-option-set-heading';
        const label=document.createElement('td');label.colSpan=5;
        const name=document.createElement('strong');name.textContent=draft.name || {color:'Color',size:'Size',weight:'Weight',dimensions:'Dimensions',custom:'Custom'}[kind];
        const open=document.createElement('button');open.type='button';open.className='product-open-option-set';open.textContent='Edit set';
        open.addEventListener('click',()=>{typeSelect.value=kind;typeSelect.dispatchEvent(new Event('change'));});label.append(name,open);heading.append(label);otherSets.append(heading);
        rows.forEach(source=>{
          const summary=document.createElement('tr');summary.className='product-option-set-row';
          const imageCell=document.createElement('td');const original=source.querySelector('.product-option-saved-photo');
          if(original){const image=document.createElement('span');image.className='product-option-saved-photo';image.style.backgroundColor=original.style.backgroundColor;[...original.childNodes].forEach(node=>image.append(node.cloneNode(true)));imageCell.append(image);}
          const nameCell=document.createElement('td');nameCell.append(source.querySelector('.product-option-saved-info').cloneNode(true));
          const price=document.createElement('td');price.textContent=source.querySelector('.product-option-price-cell .product-option-saved-number').textContent;
          const stock=document.createElement('td');stock.textContent=source.querySelector('.product-option-stock-cell .product-option-saved-number').textContent;
          const actions=document.createElement('td');const edit=document.createElement('button');edit.type='button';edit.className='product-edit-option';edit.title='Edit this set';edit.setAttribute('aria-label','Edit '+draft.name);edit.innerHTML=source.querySelector('.product-edit-option').innerHTML;
          edit.addEventListener('click',()=>{typeSelect.value=kind;typeSelect.dispatchEvent(new Event('change'));source.querySelector('.product-edit-option').click();});actions.append(edit);
          summary.append(imageCell,nameCell,price,stock,actions);otherSets.append(summary);
        });
      });
    }

    typeSelect.addEventListener('change', () => {
      if(typeSelect.disabled){typeSelect.value=previousType;return;}
      const list = div.querySelector('.options-list');
      typeDrafts.set(previousType,{rows:[...list.children],name:variationNameInput(div).value});
      list.replaceChildren();
      const draft=typeDrafts.get(typeSelect.value);
      variationNameInput(div).value = draft?.name ?? {color:'Color',size:'Size',weight:'Weight',dimensions:'Dimensions',custom:''}[typeSelect.value];
      if(draft)draft.rows.forEach(row=>list.append(row));
      if(['size','weight','dimensions'].includes(typeSelect.value)&&!list.children.length)addOptionRow(div);
      previousType=typeSelect.value;
      configureVariation(div); syncVariations();renderOtherSets();
      div.querySelector('.product-option-table-wrap').hidden=!div.querySelector('.options-list > [data-opt-uid],.product-option-set-row');
    });
    variationNameInput(div).addEventListener('input', () => { configureVariation(div); syncVariations(); });
    sizePresets.forEach(size => {
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'btn btn-sm btn-outline';
      button.textContent = size; button.style.margin = '0 4px 4px 0';
      button.addEventListener('click', () => {
        const rows = [...div.querySelectorAll('.options-list > [data-opt-uid]')];
        if (rows.some(row => row.querySelector('.opt-value').value.trim().toLowerCase() === size.toLowerCase())) return;
        const empty = rows.find(row => !row.querySelector('.opt-value').value.trim());
        if (empty) { empty.querySelector('.opt-value').value = size; configureVariation(div); syncVariations(); }
        else {
          addOptionRow(div, {value:size});
          const added=div.querySelector('.options-list').lastElementChild;added.dataset.committed='false';added.classList.add('is-editing');added.renderCompactSummary();syncVariations();
        }
      });
      div.querySelector('.size-preset-options').appendChild(button);
    });
    const customizeColor = document.createElement('button');
    customizeColor.type = 'button';
    customizeColor.className = 'btn btn-outline product-customize-color';
    customizeColor.textContent = 'Add color';
    customizeColor.addEventListener('click', () => {
      window.openProductColorPicker({
        color: '#BC387F', trigger: customizeColor, allowVariants:true,
        onApply: (color, name, children, base) => {
          const empty = [...div.querySelectorAll('.options-list > [data-opt-uid]')].find(row => !row.querySelector('.opt-value').value.trim());
          if (empty) {
            empty.querySelector('.opt-value').value = name || color;
            const input = empty.querySelector('.opt-color'); input.value = color;
            empty.querySelector('.opt-stock').value=base?.stock ?? 0;empty.querySelector('.opt-price').value=base?.price ?? '';
            (children || []).forEach(child=>empty.addChildVariant(child));
            input.dispatchEvent(new Event('input', {bubbles:true}));
            empty.commitVariant?.();
          } else addOptionRow(div, {value:name || color, color, children, stock:base?.stock ?? 0, price:base?.price ?? ''});
        }
      });
    });
    const colorTools=div.querySelector('.color-presets');colorTools.append(customizeColor);
    div.variationHeader.insertBefore(colorTools,div.variationHeader.querySelector('.var-remove-btn'));
    div.querySelector('tfoot').remove();
    (div.variationHeader || div).querySelector('.var-remove-btn').addEventListener('click', () => {
      div.querySelectorAll('[data-opt-uid]').forEach(row => optionImages.delete(row.dataset.optUid));
      div.variationHeader?.remove();
      div.remove();
      addVariationBtn.hidden = false;
      if (variationsList.children.length === 0) noVariationStock.style.display = '';
      syncVariations();
    });
    div.querySelector('.add-option-btn').addEventListener('click', () => {
      const draft=[...div.querySelectorAll('.options-list > [data-opt-uid]')].find(row=>row.dataset.committed!=='true'&&!row.querySelector('.opt-value').value.trim());
      if(draft){draft.classList.add('is-editing');draft.querySelector('.opt-value')?.focus();return;}
      addOptionRow(div);
    });

    if (prefillOptions && prefillOptions.length) {
      prefillOptions.forEach(opt => addOptionRow(div, opt));
    }
    if(prefillOptions?.length)ensureOptionDraft(div);
    div.querySelector('.product-option-table-wrap').hidden = !div.querySelector('.options-list > [data-opt-uid]');
    configureVariation(div);
  }
  addVariationBtn.textContent = 'Add main variation';
  addVariationBtn.addEventListener('click', () => { if (!variationsList.querySelector('[data-var-idx]')) addVariationRow(); });

  function ensureOptionDraft(div) {
    const type=(div.variationHeader || div).querySelector('.var-type').value;
    if(!['custom','size','weight','dimensions'].includes(type))return;
    const empty=[...div.querySelectorAll('.options-list > [data-opt-uid]')].some(row=>row.dataset.committed!=='true'&&!row.querySelector('.opt-value').value.trim());
    if(!empty)addOptionRow(div);
  }

  function addOptionRow(varDiv, prefill) {
    prefill = prefill || {};
    const optList = varDiv.querySelector('.options-list');
    const row = document.createElement('tr');
    const uid = 'opt' + (optUidCounter++);
    row.dataset.optUid = uid;
    row.className = 'product-option-row';
    row.readSizeVariants=()=>{
      const value=row.querySelector('.opt-value').value.trim();
      return value?[{value,stock:Number(row.querySelector('.opt-stock').value || 0),...(row.querySelector('.opt-price').value!==''?{price:Number(row.querySelector('.opt-price').value)}:{}),children:row.readChildren?.() || []}]:[];
    };
    row.innerHTML = `
      <td class="product-option-photo-cell"><div class="opt-photo-wrap" style="position:relative;width:44px;height:44px;flex-shrink:0"></div></td>
      <td class="product-option-name-cell"><div class="product-option-identity">
      <input type="file" class="opt-photo-input" accept="image/jpeg,image/png,image/webp" style="display:none">
      <input type="hidden" class="opt-color"><button type="button" class="opt-color-swatch" aria-label="Upload variant photo" title="Upload variant photo" hidden></button>
      <span class="product-floating-control product-option-floating-control"><input type="text" placeholder=" " class="opt-value" value="${(prefill.value || '').replace(/"/g,'&quot;')}"
        style="flex:1;border:1px solid var(--border);border-radius:7px;padding:6px 10px;font-size:12px"><span class="product-floating-caption">Option name</span><fieldset class="product-floating-outline" aria-hidden="true"><legend><span>Option name</span></legend></fieldset></span>
      <output class="opt-color-code" aria-label="Color HEX code" hidden></output>
      </div></td><td class="product-option-price-cell"><label class="option-field-label option-field-price product-floating-number"><span class="product-floating-caption">Price</span><input aria-label="Price" type="number" placeholder=" " min="0" step="0.01" class="opt-price" title="Leave blank to use the product's base price" value="${prefill.price ?? ''}"
        style="width:90px;border:1px solid var(--border);border-radius:7px;padding:6px 10px;font-size:12px"><fieldset class="product-floating-outline" aria-hidden="true"><legend><span>Price</span></legend></fieldset></label>
      <span class="product-parent-price" hidden>Per option</span></td><td class="product-option-stock-cell"><label class="option-field-label option-field-stock product-floating-number"><span class="product-floating-caption">Stock</span><input aria-label="Stock" type="number" placeholder=" " min="0" class="opt-stock" value="${prefill.stock ?? 0}"
        style="width:80px;border:1px solid var(--border);border-radius:7px;padding:6px 10px;font-size:12px"><fieldset class="product-floating-outline" aria-hidden="true"><legend><span>Stock</span></legend></fieldset></label>
      <output class="product-parent-stock" hidden></output></td><td class="product-option-actions-cell"><button type="button" class="opt-remove-btn" aria-label="Remove option" style="border:0;background:none;cursor:pointer;color:var(--muted);padding:2px">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button></td>`;
    optList.appendChild(row);
    const nested = document.createElement('details'); nested.className = 'product-child-options';
    const childSummary = document.createElement('summary'); childSummary.textContent = 'Sizes & measurements';
    const childList = document.createElement('div'); childList.className = 'product-child-list';
    const childActions = document.createElement('div'); childActions.className = 'product-child-actions';
    ['Size','Weight','Dimensions','Color'].forEach(field => {
      const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline product-add-child'; button.textContent = 'Add ' + field.toLowerCase(); button.dataset.childKind = field;
      button.addEventListener('click',()=>{
        addChildRow({enabledFields:[field]});
        if (field === 'Color') childList.lastElementChild.querySelector('[data-child-field="Color"] input').click();
      }); childActions.append(button);
    });
    const measurementsCell = document.createElement('td'); measurementsCell.className = 'product-option-measurements-cell';
    const measurementSummary=document.createElement('div');measurementSummary.className='product-saved-measurements';measurementSummary.hidden=true;
    childActions.hidden = true;
    const addDetails = document.createElement('button'); addDetails.type='button'; addDetails.className='btn btn-outline product-add-measurements'; addDetails.textContent='Customize'; addDetails.setAttribute('aria-haspopup','dialog');
    const sizeDimensions=document.createElement('div');sizeDimensions.className='product-size-dimensions';sizeDimensions.hidden=true;
    ['Width','Height'].forEach(name=>{
      const label=document.createElement('label');const caption=document.createElement('span');caption.textContent=name+' (cm)';
      const input=document.createElement('input');input.type='number';input.min='0.01';input.step='.01';input.placeholder=name;input.dataset.sizeDimension=name;
      input.addEventListener('input',()=>{
        const inputs=sizeDimensions.querySelectorAll('input');
        if(row.dataset.optionType==='size'){const active=[...inputs].some(input=>input.value);inputs.forEach(input=>input.required=active);}
        inputs[0].setCustomValidity(row.dataset.optionType==='dimensions'&&inputs[1].value&&Number(inputs[1].value)<Number(inputs[0].value)?'Maximum weight must be at least the minimum weight.':'');syncVariations();
      });label.append(caption,input);sizeDimensions.append(label);
    });
    const dimensionToggle=document.createElement('button');dimensionToggle.type='button';dimensionToggle.className='btn btn-outline product-size-dimensions-toggle';dimensionToggle.textContent='Add dimensions';
    dimensionToggle.addEventListener('click',()=>{
      const isWeight=row.dataset.optionType==='dimensions';
      sizeDimensions.hidden=!sizeDimensions.hidden;dimensionToggle.textContent=(sizeDimensions.hidden?'Add ':'Remove ')+(isWeight?'weight':'dimensions');
      sizeDimensions.querySelectorAll('input').forEach((input,index)=>{input.required=!sizeDimensions.hidden&&(!isWeight||index===0);if(sizeDimensions.hidden){input.value='';input.setCustomValidity('');}});
      if(!sizeDimensions.hidden)sizeDimensions.querySelector('input').focus();syncVariations();
    });
    addDetails.addEventListener('click',()=>{
      const mainType=(varDiv.variationHeader || varDiv).querySelector('.var-type').value;
      const currentChildren=row.readChildren();
      const currentColor=currentChildren.find(child=>child.attributes.Color)?.attributes.Color || row.querySelector('.opt-color').value || '#000000';
      window.openProductColorPicker({
        trigger:addDetails,allowVariants:true,editing:currentChildren.length>0,color:/^#[0-9a-f]{6}$/i.test(currentColor)?currentColor:'#000000',
        children:currentChildren,extraFields:childFields.filter(name=>name!=='Color'),
        stock:row.querySelector('.opt-stock').value,price:row.querySelector('.opt-price').value,
        onApply:(color,name,children,base)=>{
          row.querySelector('.opt-stock').value=base.stock;row.querySelector('.opt-price').value=base.price;
          childList.replaceChildren();
          const selected=children.length?children:mainType!=='color'?[{attributes:{},stock:base.stock,price:base.price}]:[];
          selected.forEach(child=>addChildRow({...child,attributes:{...child.attributes,...(mainType!=='color'?{Color:name || color}:{})}}));
          if(mainType==='color'){
            const colorField=row.querySelector('.opt-color');colorField.value=color;colorField.dispatchEvent(new Event('input',{bubbles:true}));
          }
          updateParentStock();row.renderCompactSummary?.();syncVariations();
        }
      });
    });
    nested.append(childSummary, childList, childActions); measurementsCell.append(nested,sizeDimensions,measurementSummary); row.append(measurementsCell);
    const updateParentStock = () => {
      const hasChildren = childList.children.length > 0;
      const first=childList.firstElementChild?.readVariant();
      const required=childFields.filter(name=>name!==variationNameInput(varDiv).value&&first?.attributes[name]&&first.attributes[name]!==NestedProductVariants.NONE);
      childList.querySelectorAll('[data-child-field]').forEach(label=>{
        const input=label.querySelector('input,select');input.required=required.includes(label.dataset.childField);
        if(input.required){label.hidden=false;label.dataset.enabled='true';}
      });
      const mainType=(varDiv.variationHeader || varDiv).querySelector('.var-type').value;
      const allowedKinds=['Size','Weight','Dimensions','Color'].filter(kind=>(kind!=='Color'||mainType==='custom')&&!(['size','weight'].includes(mainType)&&variationType(kind)===mainType));
      childSummary.textContent = hasChildren ? childList.children.length + (childList.children.length===1?' option':' options') : '';
      childSummary.hidden = true;
      nested.open = true;
      const mainKind = childList.firstElementChild?.querySelector('[data-child-field][data-enabled="true"]')?.dataset.childField;
      childActions.querySelectorAll('button').forEach(button=>{
        button.hidden = !allowedKinds.includes(button.dataset.childKind) || hasChildren && !!mainKind && button.dataset.childKind!==mainKind;
        button.textContent = (hasChildren && mainKind?'Add another ':'Add ') + button.dataset.childKind.toLowerCase();
      });
      row.querySelectorAll('.option-field-label').forEach(label => { label.hidden = hasChildren; label.querySelector('input').disabled = hasChildren; });
      row.querySelector('.product-parent-price').hidden=true;
      row.querySelector('.product-parent-stock').hidden=true;
    };
    function addChildRow(prefill = {}) {
      const child = document.createElement('div'); child.className = 'product-child-row';
      const inputs = {};
      const enabledFields = new Set(prefill.enabledFields || Object.keys(prefill.attributes || {}).filter(key => prefill.attributes[key] && prefill.attributes[key] !== NestedProductVariants.NONE));
      const primaryField = childFields.find(name => enabledFields.has(name));
      childFields.forEach(name => {
        const label = document.createElement('label'); label.dataset.childField = name;
        const caption = document.createElement('span'); caption.textContent = name;
        const input = document.createElement(name === 'Size' ? 'select' : 'input'); if (name !== 'Size') { input.type = 'text'; input.maxLength = 100; }
        input.placeholder = name === 'Size' ? 'e.g. Small' : name === 'Weight' ? 'e.g. 250 g' : name === 'Dimensions' ? 'e.g. 20 x 8 cm' : name;
        const savedValue = prefill.attributes?.[name] === NestedProductVariants.NONE ? '' : prefill.attributes?.[name] || '';
        if (name === 'Size') {
          const placeholder = new Option('Select size', ''); input.append(placeholder);
          const choices = [...sizePresets]; if (savedValue && !choices.includes(savedValue)) choices.push(savedValue);
          choices.forEach(size => input.append(new Option(size, size)));
          input.addEventListener('change', syncVariations);
        }
        input.value = savedValue;
        if (name === 'Color') {
          input.readOnly = true; input.setAttribute('aria-label','Color');
          input.addEventListener('click',()=>window.openProductColorPicker({input,color:input.value || '#000000',trigger:input}));
        }
        label.dataset.enabled = String(enabledFields.has(name));
        label.hidden = !enabledFields.has(name) || name.toLowerCase() === variationNameInput(varDiv).value.trim().toLowerCase();
        input.disabled=true;
        input.addEventListener('input',()=>{updateParentStock();syncVariations();});input.addEventListener('change',()=>{updateParentStock();syncVariations();}); inputs[name] = input; label.append(caption,input); child.append(label);
      });
      const stockInputs = {};
      ['stock','price'].forEach(name => {
        const label = document.createElement('label'); const caption = document.createElement('span'); caption.textContent = name === 'stock' ? 'Stock' : 'Price';
        const input = document.createElement('input'); input.type = 'number'; input.min = 0; input.step = name === 'stock' ? '1' : '.01'; input.required = name === 'stock'; input.placeholder = name === 'stock' ? '0' : 'Base price';
        input.value = prefill[name] ?? '';input.disabled=true; stockInputs[name] = input; input.addEventListener('input', syncVariations); label.append(caption,input); child.append(label);
      });
      child.readVariant = () => ({attributes:Object.fromEntries(Object.entries(inputs).map(([key,input])=>[key,input.value.trim()])), stock:stockInputs.stock.value === '' ? null : Number(stockInputs.stock.value), price:stockInputs.price.value});
      const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'product-remove-child'; remove.innerHTML = '&times;'; remove.title = primaryField ? 'Remove ' + primaryField.toLowerCase() + ' option' : 'Remove option'; remove.setAttribute('aria-label',remove.title); remove.addEventListener('click',()=>{child.remove();updateParentStock();syncVariations();});
      child.append(remove);
      childList.append(child); nested.open=true; updateParentStock(); syncVariations();
    }
    row.updateSummary = updateParentStock;
    row.addChildVariant = addChildRow;
    row.readChildren = () => {
      if(row.dataset.optionType==='dimensions'&&!sizeDimensions.hidden){
        const [min,max]=[...sizeDimensions.querySelectorAll('input')].map(input=>input.value);
        return min?[{attributes:{Weight:min+(max?' - '+max:'')+' g'},stock:Number(row.querySelector('.opt-stock').value),price:row.querySelector('.opt-price').value}]:[];
      }
      if(['size','weight'].includes(row.dataset.optionType)&&!sizeDimensions.hidden){
        const width=sizeDimensions.querySelector('[data-size-dimension=Width]').value,height=sizeDimensions.querySelector('[data-size-dimension=Height]').value;
        return width&&height?[{attributes:{Dimensions:width+' x '+height+' cm'},stock:Number(row.querySelector('.opt-stock').value),price:row.querySelector('.opt-price').value}]:[];
      }
      return [...childList.children].map(child=>child.readVariant());
    };
    row.initializeChildren = () => {
      const children=prefill.children || [];
      const type=(varDiv.variationHeader || varDiv).querySelector('.var-type').value;
      const optionalField=type==='dimensions'?'Weight':'Dimensions';
      const simpleDimensions=['size','weight','dimensions'].includes(type)&&children.length===1&&children[0].attributes[optionalField]&&Object.entries(children[0].attributes).every(([name,value])=>name===optionalField||name===variationNameInput(varDiv).value||!value||value===NestedProductVariants.NONE);
      if(simpleDimensions){
        const values=children[0].attributes[optionalField].match(/\d+(?:\.\d+)?/g)||[];
        sizeDimensions.hidden=false;dimensionToggle.textContent=type==='dimensions'?'Remove weight':'Remove dimensions';sizeDimensions.querySelectorAll('input').forEach((input,index)=>{input.value=values[index] || '';input.required=type!=='dimensions'||index===0;});
        row.querySelector('.opt-stock').value=children[0].stock;row.querySelector('.opt-price').value=children[0].price ?? '';
      }else children.forEach(child=>addChildRow(child));nested.open=true;
    };
    nested.addEventListener('invalid',()=>{nested.open=true;},true);

    const colorInput = row.querySelector('.opt-color');
    colorInput.value = /^#[0-9a-f]{6}$/i.test(prefill.color || '') ? prefill.color : (namedColor(prefill.value || '') || '#000000');
    if (prefill.color || namedColor(prefill.value || '')) row.dataset.color = colorInput.value;
    if (prefill.color) row.dataset.customColor = 'true';
    const colorCode = row.querySelector('.opt-color-code');
    const showColorCode = () => { colorCode.textContent = colorInput.value.toUpperCase(); const swatch = row.querySelector('.opt-color-swatch'); swatch.style.backgroundColor = colorInput.value; swatch.setAttribute('aria-label', 'Color ' + colorInput.value.toUpperCase()); };
    showColorCode();
    row.querySelector('.opt-value').addEventListener('input', () => { const color = namedColor(row.querySelector('.opt-value').value); if (color && !row.dataset.customColor) { colorInput.value = color; row.dataset.color = color; showColorCode(); } });
    colorInput.addEventListener('input', () => { row.dataset.color = colorInput.value; row.dataset.customColor = 'true'; showColorCode(); syncVariations(); });
    row.initializeChildren();updateParentStock();
    configureVariation(varDiv);
    row.querySelectorAll('.opt-value, .opt-price, .opt-stock').forEach(el => el.addEventListener('input', syncVariations));
    row.querySelector('.opt-remove-btn').addEventListener('click', () => { row.remove(); optionImages.delete(uid); syncVariations(); });

    const photoWrap  = row.querySelector('.opt-photo-wrap');
    const photoInput = row.querySelector('.opt-photo-input');
    row.querySelector('.opt-color-swatch').addEventListener('click',()=>photoInput.click());
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

    const info = document.createElement('div'); info.className='product-option-saved-info';
    const swatch = document.createElement('span');swatch.className='product-option-saved-swatch';
    const copy=document.createElement('div');const title=document.createElement('strong');const code=document.createElement('small');const measurements=document.createElement('small');copy.append(title,code,measurements);info.append(swatch,copy);
    row.querySelector('.product-option-name-cell').prepend(info);
    const photo=document.createElement('button');photo.type='button';photo.className='product-option-saved-photo';photo.title='Change variant photo';photo.setAttribute('aria-label','Change variant photo');photo.addEventListener('click',()=>photoInput.click());row.querySelector('.product-option-photo-cell').prepend(photo);
    const clearPhoto=document.createElement('button');clearPhoto.type='button';clearPhoto.className='product-option-clear-photo';clearPhoto.innerHTML='&times;';clearPhoto.title='Remove variant photo';clearPhoto.setAttribute('aria-label','Remove variant photo');
    clearPhoto.addEventListener('click',()=>photoWrap.querySelector('.opt-photo-remove')?.click());row.querySelector('.product-option-photo-cell').append(clearPhoto);
    const savedPrice=document.createElement('span');savedPrice.className='product-option-saved-number';row.querySelector('.product-option-price-cell').prepend(savedPrice);
    const savedStock=document.createElement('span');savedStock.className='product-option-saved-number';row.querySelector('.product-option-stock-cell').prepend(savedStock);
    const actions=row.querySelector('.product-option-actions-cell');const remove=row.querySelector('.opt-remove-btn');
    const controls=document.createElement('div');controls.className='product-option-row-actions';
    const edit=document.createElement('button');edit.type='button';edit.className='product-edit-option';edit.setAttribute('aria-label','Edit variant');edit.title='Edit variant';
    edit.innerHTML='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 5 5-12 12-6 1 1-6L16 3Z"/><path d="m14 5 5 5"/></svg>';
    const commit=document.createElement('button');commit.type='button';commit.className='product-commit-option';
    const money=value=>String.fromCharCode(8369)+Number(value).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
    row.renderCompactSummary=()=>{
      title.textContent=row.querySelector('.opt-value').value.trim();
      const isColor=(varDiv.variationHeader || varDiv).querySelector('.var-type').value==='color';
      swatch.hidden=true;swatch.style.backgroundColor=colorInput.value;
      code.textContent=isColor?colorInput.value.toUpperCase():'';
      const children=row.readChildren();
      measurementSummary.replaceChildren();
      const savedMeasurements=children;
      savedMeasurements.forEach((item,index)=>{
        const entry=document.createElement('dl');entry.className='product-saved-measurement-row';
        const fields=Object.entries(item.attributes).filter(([name,value])=>value&&value!==NestedProductVariants.NONE&&name!==variationNameInput(varDiv).value||name==='Size'&&value);
        fields.push(['Stock',item.stock==null?'Set stock':item.stock],['Price',item.price!==''&&item.price!=null?money(item.price):'Base price']);
        fields.forEach(([name,value])=>{const cell=document.createElement('div'),label=document.createElement('dt'),content=document.createElement('dd');label.textContent=name;content.textContent=value;cell.append(label,content);entry.append(cell);});
        const editMeasurement=document.createElement('button');editMeasurement.type='button';editMeasurement.className='product-measurement-edit';editMeasurement.title='Edit option details';editMeasurement.setAttribute('aria-label','Edit '+(item.attributes.Size || 'option '+(index+1)));
        editMeasurement.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 5 5-12 12-6 1 1-6L16 3Z"/><path d="m14 5 5 5"/></svg>';
        editMeasurement.addEventListener('click',()=>{
          if(['size','weight','dimensions'].includes(row.dataset.optionType)){edit.click();return;}
          addDetails.click();const editor=document.querySelector('.product-color-modal.open [data-color-options]')?.children[index];
          if(editor){editor.scrollIntoView({block:'nearest'});editor.querySelector('[data-option-field=Stock]')?.focus();}
        });entry.append(editMeasurement);measurementSummary.append(entry);
      });
      measurementSummary.hidden=!savedMeasurements.length;
      const values=[...new Set(children.map(child=>Object.values(child.attributes).filter(Boolean).join(' / ')).filter(Boolean))];
      measurements.textContent=values.join(', ');measurements.hidden=!values.length||!measurementSummary.hidden;
      const base=form.elements.namedItem('price')?.value || '';
      const priceRows=children;
      const prices=priceRows.length?priceRows.map(child=>child.price!==''&&child.price!=null?child.price:base):[row.querySelector('.opt-price').value || base];
      if(prices.every(price=>price===''))savedPrice.textContent='Base price';
      else {const numbers=prices.map(Number);const min=Math.min(...numbers),max=Math.max(...numbers);savedPrice.textContent=min===max?money(min):money(min)+' - '+money(max);}
      savedStock.textContent=String(priceRows.length?priceRows.reduce((sum,child)=>sum+Number(child.stock || 0),0):Number(row.querySelector('.opt-stock').value || 0));
      const image=photoWrap.querySelector('img');
      if(isColor)row.querySelector('.product-option-photo-cell').append(row.querySelector('.opt-color-swatch'));
      row.querySelector('.opt-color-swatch').hidden=!isColor || !!image;
      photoWrap.hidden=isColor && !image;
      row.dataset.preview=image?'image':isColor?'color':'empty';
      clearPhoto.hidden=!image;
      photo.replaceChildren();photo.style.backgroundColor=!image&&isColor?colorInput.value:'#fcfafb';
      if(image){const thumbnail=document.createElement('img');thumbnail.src=image.src;thumbnail.alt=title.textContent;photo.append(thumbnail);}
      commit.textContent=row.dataset.committed==='true'?'Done':'Add';
    };
    row.commitVariant=()=>{
      const name=row.querySelector('.product-main-weight-fields:not([hidden]) input') || row.querySelector('.product-main-size-select:not([hidden])') || row.querySelector('.opt-value');
      name.setCustomValidity(name.value.trim()?'':'Choose or enter an option name.');
      if(!name.validity.valid){showFormError(name.validationMessage,name);return false;}
      for(const input of row.querySelectorAll('input,select')){if(!input.disabled && !input.validity.valid){showFormError(input.validationMessage,input);return false;}}
      const children=row.readChildren();
      try{NestedProductVariants.build(variationNameInput(varDiv).value,[{value:row.querySelector('.opt-value').value,stock:Number(row.querySelector('.opt-stock').value || 0),children}],childFields);}catch(error){showFormError(error.message,addDetails.hidden?name:addDetails);return false;}
      const requiredFields=childFields.filter(name=>children[0]?.attributes[name]&&children[0].attributes[name]!==NestedProductVariants.NONE&&name!==variationNameInput(varDiv).value);
      if(children.some(child=>requiredFields.some(name=>!child.attributes[name]||child.attributes[name]===NestedProductVariants.NONE))){
        combinationPanel.querySelector('span').textContent='Complete '+requiredFields.join(', ')+' for every option to match the first option.';combinationPanel.hidden=false;nested.open=true;return false;
      }
      if(children.some(child=>!Object.values(child.attributes).some(Boolean))){nested.open=true;const input=childList.querySelector('label:not([hidden]) :is(input,select)');input?.focus();return false;}
      syncVariations();
      if(combinationError&&row.dataset.committed!=='true'){showFormError(combinationError,name);return false;}
      formError.hidden=true;
      row.dataset.committed='true';row.classList.remove('is-editing');nested.open=false;row.renderCompactSummary();syncVariations();ensureOptionDraft(varDiv);return true;
    };
    edit.addEventListener('click',()=>{
      if((varDiv.variationHeader || varDiv).querySelector('.var-type').value==='color'){
        window.openProductColorPicker({editing:true,trigger:edit,allowVariants:true,color:colorInput.value,name:row.querySelector('.opt-value').value,children:row.readChildren(),extraFields:childFields.filter(name=>name!=='Color'),stock:row.querySelector('.opt-stock').value,price:row.querySelector('.opt-price').value,
          onApply:(color,name,children,base)=>{
            row.querySelector('.opt-value').value=name || color;
            colorInput.value=color;row.dataset.color=color;row.dataset.customColor='true';
            row.querySelector('.opt-stock').value=base.stock;row.querySelector('.opt-price').value=base.price;
            childList.replaceChildren();children.forEach(child=>addChildRow(child));updateParentStock();showColorCode();row.commitVariant();
          }
        });return;
      }
      row.classList.add('is-editing');nested.open=true;row.querySelector('.opt-value:not([hidden]),.product-main-size-select:not([hidden]),.product-main-weight-fields:not([hidden]) input')?.focus();
    });
    commit.addEventListener('click',()=>row.commitVariant());
    row.addEventListener('invalid',()=>{row.classList.add('is-editing');nested.open=true;},true);
    row.querySelector('.opt-value').addEventListener('input',event=>event.target.setCustomValidity(''));
    row.querySelector('.product-main-size-select')?.addEventListener('change',event=>event.target.setCustomValidity(''));
    row.dataset.committed=prefill.value?'true':'false';row.classList.toggle('is-editing',!prefill.value);
    controls.append(edit,addDetails,dimensionToggle,commit,remove);actions.append(controls);
    configureVariation(varDiv);
    row.renderCompactSummary();
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
    row?.renderCompactSummary?.();
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
    if (initializingVariations) return;
    combinationError = ''; combinationPanel.hidden = true;
    const groups=[...variationsList.querySelectorAll('[data-var-idx]')];
    const allParents=[];const populatedGroups=[];
    if(!groups.length){variationsJson.value='';noVariationStock.style.display='';syncPackageFields([]);return;}
    const hasAddedVariations=groups.some(group=>group.dataset.savedGroup==='true'||group.dataset.typeLocked==='true'||[...group.querySelectorAll('.options-list > [data-opt-uid]')].some(row=>row.dataset.committed==='true'&&row.querySelector('.opt-value').value.trim()));
    for(const group of groups){

    const selector=(group.variationHeader || group).querySelector('.var-type');
    const locked=hasAddedVariations;
    selector.disabled=locked;selector.title=locked?'Remove the added variations and options before changing the option type.':'';
    group.querySelectorAll('.product-open-option-set,.product-other-option-sets .product-edit-option').forEach(button=>button.disabled=locked);
    group.querySelector('.product-option-table-wrap').hidden=!group.querySelector('.options-list > [data-opt-uid],.product-option-set-row');
    const name = variationNameInput(group).value.trim();
    const parents = [...group.querySelectorAll('.options-list > [data-opt-uid]')].flatMap(row => {
      row.updateSummary?.();
      row.renderCompactSummary?.();
      const option = {value:row.querySelector('.opt-value').value.trim(), stock:Number(row.querySelector('.opt-stock').value || 0), children:row.readChildren?.() || []};
      const price = row.querySelector('.opt-price').value;
      if (price !== '') option.price = Number(price);
      if ((group.variationHeader || group).querySelector('.var-type').value === 'color' && row.dataset.color) option.color = row.dataset.color;
      if (optionImages.has(row.dataset.optUid)) option.image_key = row.dataset.optUid;
      else if (row.dataset.existingImage) option.existing_image = row.dataset.existingImage;
      else if(optionImages.has(group.dataset.groupImageKey))option.image_key=group.dataset.groupImageKey;
      else if(group.dataset.groupExistingImage)option.existing_image=group.dataset.groupExistingImage;
      if(selector.value==='size')return row.readSizeVariants().map(size=>({...size,...(option.image_key?{image_key:option.image_key}:{}),...(option.existing_image?{existing_image:option.existing_image}:{})}));
      return [option];
    });
    const valid=parents.filter(parent=>parent.value);
    if(valid.length){populatedGroups.push(name);allParents.push(...valid.map(parent=>({...parent,variation_name:name,option_value:parent.value})));}
    }
    try {
      if(populatedGroups.some(name=>!name))throw new Error('Choose a name for the main variation.');
      if(new Set(populatedGroups.map(name=>name.toLowerCase())).size!==populatedGroups.length)throw new Error('Use a different variation name for each group.');
      const multiple=populatedGroups.length>1;
      const name=multiple?'Variation':populatedGroups[0] || '';
      const parents=allParents.map(parent=>multiple?{...parent,value:parent.variation_name+' / '+parent.option_value}:parent);
      const encoded=NestedProductVariants.build(name,parents,childFields,existingCombination?.axes.map(axis=>axis.name) || []);
      const editorGroups=groups.filter(group=>variationNameInput(group).value.trim()).map(group=>({name:variationNameInput(group).value.trim(),saved:group.dataset.savedGroup==='true',...(optionImages.has(group.dataset.groupImageKey)?{image_key:group.dataset.groupImageKey}:group.dataset.groupExistingImage?{existing_image:group.dataset.groupExistingImage}:{})}));
      if(editorGroups.length){if(!encoded.length)encoded.push({name:'Options',options:[]});encoded[0].editor_groups=editorGroups;}
      variationsJson.value=JSON.stringify(encoded);
    } catch(error) {
      const incomplete = error.message.startsWith('Enter a size, weight or dimensions');
      combinationError = incomplete ? 'Select an option before saving.' : error.message;
      combinationPanel.querySelector('span').textContent = incomplete ? '' : error.message; combinationPanel.hidden = incomplete; variationsJson.value = '';
    }
    syncPackageFields(JSON.parse(variationsJson.value || '[]'));
  }

  function syncOptionImageInputs() {
    form.querySelectorAll('.opt-image-file-input').forEach(el => el.remove());
    const activeOptionIds = new Set([...variationsList.querySelectorAll('[data-opt-uid]')].map(row=>row.dataset.optUid).concat([...variationsList.querySelectorAll('[data-group-image-key]')].map(group=>group.dataset.groupImageKey)));
    optionImages.forEach((file, uid) => {
      if (!activeOptionIds.has(uid)) return;
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

  function restoreNamedGroups(name,parents){
    parents=parents.map(parent=>{
      const children=parent.children || [];
      if(children.length===1&&!Object.entries(children[0].attributes).some(([key,value])=>key!==name&&value&&value!==NestedProductVariants.NONE))return {...parent,stock:children[0].stock,price:children[0].price,children:[]};
      return parent;
    });
    if(parents.some(parent=>parent.variation_name)){
      const buckets=new Map();parents.forEach(parent=>{const key=parent.variation_name || name;if(!buckets.has(key))buckets.set(key,[]);buckets.get(key).push({...parent,value:parent.option_value || parent.value});});
      buckets.forEach((options,label)=>addVariationRow(label,options));
    }else addVariationRow(name,parents);
  }
  if (existingCombination) {
    const parents=primaryAxis.options.map(option=>({...option,children:existingCombination.options.filter(sku=>sku.attributes[primaryAxis.name]===option.value).map(sku=>({attributes:sku.attributes,stock:existingCombination.requires_stock_setup?'':sku.stock,price:sku.price ?? ''}))}));
    restoreNamedGroups(primaryAxis.name,parents);
  }else (opts.existingVariations || []).forEach(group=>{if(group.options.length||!existingEditorGroups.length)restoreNamedGroups(group.name,group.options);});
  existingEditorGroups.forEach(metadata=>{
    let group=[...variationsList.querySelectorAll('[data-var-idx]')].find(group=>variationNameInput(group).value===metadata.name);
    if(!group){addVariationRow(metadata.name,[]);group=variationsList.lastElementChild;}
    if(metadata.saved){group.dataset.savedGroup='true';group.hidden=true;if(group.variationHeader)group.variationHeader.hidden=true;}
  });
  if(existingEditorGroups.some(group=>group.saved)){refreshGroupTable();if(!variationsList.querySelector('[data-var-idx]:not([data-saved-group=true])'))addVariationRow('');}
  const restoredNamedGroups=[...variationsList.querySelectorAll('[data-var-idx]')].filter(group=>variationNameInput(group).value.trim());
  if(restoredNamedGroups.length>1){
    restoredNamedGroups.forEach(group=>{group.dataset.savedGroup='true';group.hidden=true;if(group.variationHeader)group.variationHeader.hidden=true;});refreshGroupTable();if(!variationsList.querySelector('[data-var-idx]:not([data-saved-group=true])'))addVariationRow('');
  }
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
    opts.existingDetails.filter(d => !['weight','dimensions','condition'].includes((d.label || '').trim().toLowerCase())).forEach(d => addDetailRow(d));
  }


  const automaticDetails = document.createElement('dl');
  automaticDetails.className = 'product-automatic-details';
  detailsList.before(automaticDetails);
  const automaticHint = document.createElement('p');
  automaticHint.className = 'product-automatic-hint';
  automaticHint.textContent = 'Weight, dimensions and condition update from the fields above. Add other details below.';
  automaticDetails.before(automaticHint);
  function renderAutomaticDetails() {
    const value = name => form.elements.namedItem(name)?.value || '';
    const rows = [];
    if (Number(value('weight_grams')) > 0) {
      let weight = String(Number(value('weight_grams')));
      if (Number(value('weight_grams_max')) > Number(value('weight_grams'))) weight += ' - ' + Number(value('weight_grams_max'));
      rows.push(['Weight', weight + ' g']);
    }
    const dimensionKeys = form.elements.namedItem('length_cm') ? ['length_cm','width_cm','height_cm'] : ['width_cm','height_cm'];
    const dimensions = dimensionKeys.map(name => Number(value(name)));
    if (dimensions.every(n => n > 0)) rows.push(['Dimensions', dimensions.join(' \u00d7 ') + (dimensionKeys.length===3?' cm (L \u00d7 W \u00d7 H)':' cm (W \u00d7 H)')]);
    const condition = value('condition');
    if (condition) rows.push(['Condition', condition.charAt(0).toUpperCase() + condition.slice(1)]);
    automaticDetails.replaceChildren();
    rows.forEach(([label, text]) => {
      const row = document.createElement('div');
      const term = document.createElement('dt'); term.textContent = label;
      const description = document.createElement('dd'); description.textContent = text;
      row.append(term, description); automaticDetails.append(row);
    });
  }
  ['weight_grams','weight_grams_max','length_cm','width_cm','height_cm','condition'].forEach(name => {
    const field = packageFields.get(name) || form.elements.namedItem(name);
    field?.addEventListener('input', renderAutomaticDetails);
    field?.addEventListener('change', renderAutomaticDetails);
  });
  packageDetailsReady=true;renderAutomaticDetails();
  floatProductFields();

  // Sync everything right before submit
  form.addEventListener('submit', (e) => {
    syncVariations();
    if (combinationError) {
      e.preventDefault();
      if (combinationError) {
        combinationPanel.querySelector('span').textContent = combinationError;
        combinationPanel.hidden = false;
        showFormError(combinationError, combinationPanel);
        const name = variationsList.querySelector('.var-name-input');
        if (name && !name.value.trim()) name.focus({preventScroll:true});
      }
      return;
    }
    const invalid=Array.from(form.elements).find(input=>input.willValidate && !input.validity.valid);
    if(invalid){e.preventDefault();showFormError(invalid.validationMessage,invalid);return;}
    if (totalPhotoCount() === 0 && optionImages.size === 0 && !variationsList.querySelector('[data-existing-image],[data-group-existing-image]')) {
      e.preventDefault();
      showFormError('Add at least one product photo, either a cover photo or a photo on a variation option.');
      return;
    }
    syncImages();
    syncVariations();
    syncOptionImageInputs();
    syncDetails();
    formError.hidden=true;
  });
};
</script>
