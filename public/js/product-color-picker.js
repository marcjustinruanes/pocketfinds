(() => {
  const clamp = (n, max = 1) => Math.max(0, Math.min(max, n));
  function rgb(h, s, v) {
    const f = n => { const k = (n + h / 60) % 6; return Math.round(255 * v * (1 - s * Math.max(0, Math.min(k, 4 - k, 1)))); };
    return [f(5), f(3), f(1)];
  }
  const hex = values => '#' + values.map(n => n.toString(16).padStart(2, '0')).join('').toUpperCase();
  function hsv(values) {
    const [r, g, b] = values.map(n => n / 255), max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min;
    let h = !d ? 0 : max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
    return [(h * 60 + 360) % 360, max ? d / max : 0, max];
  }
  function parse(value) {
    let text = value.trim().replace(/^#/, '');
    if (/^[a-f\d]{3}$/i.test(text)) text = [...text].map(c => c + c).join('');
    return /^[a-f\d]{6}$/i.test(text) ? text.match(/../g).map(n => parseInt(n, 16)) : null;
  }
  if (typeof module !== 'undefined') module.exports = {rgb, hsv, hex, parse};
  if (typeof document === 'undefined') return;
  let modal, target, h = 0, s = 0, v = 1, original, onApply, trigger, allowVariants, addVariantRow, variantFields=['Size','Weight','Dimensions'];
  const field = name => modal.querySelector(`[data-color-${name}]`);
  function syncRequiredMeasurements() {
    const rows=[...field('options').children],first=rows[0];
    if(!first)return;
    const names=variantFields.filter(name=>name!=='Dimensions');names.push('Width','Height');
    const required=new Set(names.filter(name=>first.querySelector('[data-option-field="'+name+'"]')?.value.trim()));
    field('error').textContent='';
    rows.forEach(row=>{
      row.querySelectorAll('[data-option-field]:not([type=hidden])').forEach(input=>{
        if(row!==first&&names.includes(input.dataset.optionField)&&!required.has(input.dataset.optionField))input.value='';
        input.required=required.has(input.dataset.optionField)||input.dataset.optionField==='Stock'&&rows.length>1;
        const label=input.closest('label'),caption=label.querySelector('.product-palette-caption'),legend=label.querySelector('legend span');
        if(caption){
          if(!caption.dataset.baseText)caption.dataset.baseText=caption.textContent;
          caption.textContent=caption.dataset.baseText;
          if(legend)legend.textContent=caption.textContent;
        }
        if(input.value.trim()||!input.required)input.removeAttribute('aria-invalid');
      });
      const dimension=row.querySelector('[data-option-field=Dimensions]');
      if(dimension){const width=row.querySelector('[data-option-field=Width]').value,height=row.querySelector('[data-option-field=Height]').value;dimension.value=width&&height?width+' x '+height+' cm':'';}
    });
  }
  function floatLabels(container) {
    container.querySelectorAll('label').forEach(label=>{
      const input=label.querySelector('input:not([type=hidden]):not([type=range]),select');
      if(!input||label.classList.contains('product-palette-floating'))return;
      let caption=label.querySelector(':scope > span');
      if(!caption){caption=document.createElement('span');caption.textContent=[...label.childNodes].filter(node=>node.nodeType===Node.TEXT_NODE).map(node=>node.textContent).join('').trim();}
      [...label.childNodes].filter(node=>node.nodeType===Node.TEXT_NODE).forEach(node=>node.remove());
      caption.className='product-palette-caption';label.classList.add('product-palette-floating');
      if(input.tagName==='SELECT')label.classList.add('is-select');
      input.placeholder=' ';
      const outline=document.createElement('fieldset');outline.className='product-palette-outline';outline.setAttribute('aria-hidden','true');
      const legend=document.createElement('legend'),text=document.createElement('span');text.textContent=caption.textContent;legend.append(text);outline.append(legend);
      label.append(caption,outline);
    });
  }
  function paint(updateCodes = true) {
    const values = rgb(h, s, v), color = hex(values);
    field('canvas').style.backgroundColor = hex(rgb(h, 1, 1));
    field('cursor').style.left = `${s * 100}%`; field('cursor').style.top = `${(1 - v) * 100}%`;
    field('preview').style.background = color; field('hue').value = h;
    field('canvas').setAttribute('aria-valuenow', Math.round(s * 100));
    field('canvas').setAttribute('aria-valuetext', `Saturation ${Math.round(s * 100)}%, brightness ${Math.round(v * 100)}%`);
    if (updateCodes) { field('hex').value = color; ['r','g','b'].forEach((key, i) => field(key).value = values[i]); }
    field('error').textContent = ''; field('apply').disabled = false;
    field('shades').replaceChildren();
    for (const brightness of [.15, .3, .45, .6, .75, .9, 1]) {
      const shade = hex(rgb(h, s, brightness));
      const button = document.createElement('button'); button.type = 'button'; button.style.background = shade;
      button.title = shade; button.setAttribute('aria-label', `Use shade ${shade}`);
      button.addEventListener('click', () => { v = brightness; paint(); }); field('shades').append(button);
    }
  }
  function close() { modal.classList.remove('open'); (trigger || target)?.focus(); target = null; onApply = null; trigger = null; }
  function create() {
    modal = document.createElement('div'); modal.className = 'modal-overlay product-color-modal';
    modal.innerHTML = `<div class="modal product-color-dialog" role="dialog" aria-modal="true" aria-labelledby="productColorTitle">
      <div class="modal-head"><div><h3 id="productColorTitle">Customize color</h3><p>Choose a shade or enter an exact color code.</p></div><button type="button" data-color-close aria-label="Close color picker">&times;</button></div>
      <div class="modal-body"><div class="product-color-canvas" data-color-canvas role="slider" tabindex="0" aria-label="Saturation and brightness" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span data-color-cursor></span></div>
      <label class="product-color-hue">Hue<input type="range" min="0" max="359" step="1" data-color-hue></label>
      <div class="product-color-previews"><div><span data-color-original></span><small>Original</small></div><div><span data-color-preview></span><small>New color</small></div></div>
      <label class="product-color-name">Color name (optional)<input type="text" maxlength="80" data-color-name placeholder="e.g. Dusty rose"></label><div class="product-color-nested" data-color-variants><div data-color-options></div><button class="btn btn-outline" type="button" data-color-add-row>Add another option</button></div><div class="product-color-base-fields" data-color-base><label>Stock<input type="number" min="0" step="1" data-color-stock placeholder="0"></label><label>Price (optional)<input type="number" min="0" step="0.01" data-color-price placeholder="Base price"></label></div><div class="product-color-codes"><label>HEX<input type="text" maxlength="7" data-color-hex spellcheck="false"></label><label>R<input type="number" min="0" max="255" data-color-r></label><label>G<input type="number" min="0" max="255" data-color-g></label><label>B<input type="number" min="0" max="255" data-color-b></label></div>
      <p class="product-color-error" data-color-error aria-live="polite"></p><label class="product-color-shades-label">Shades</label><div class="product-color-shades" data-color-shades></div></div>
      <div class="modal-foot"><button type="button" class="btn btn-outline" data-color-cancel>Cancel</button><button type="button" class="btn btn-primary" data-color-apply>Use color</button></div></div>`;
    const body=modal.querySelector('.modal-body');
    const workspace=document.createElement('div');workspace.className='product-color-workspace';
    const palette=document.createElement('div');palette.className='product-color-palette-panel';
    const details=document.createElement('div');details.className='product-color-details-panel';
    ['.product-color-canvas','.product-color-hue'].forEach(selector=>palette.append(body.querySelector(selector)));
    ['.product-color-name','.product-color-codes','.product-color-previews','.product-color-shades-label','.product-color-shades'].forEach(selector=>details.append(body.querySelector(selector)));
    const measurements=document.createElement('div');measurements.className='product-color-measurements-panel';
    ['.product-color-nested','.product-color-base-fields','.product-color-error'].forEach(selector=>measurements.append(body.querySelector(selector)));
    workspace.append(palette,details,measurements);body.append(workspace);
    floatLabels(body);
    document.body.append(modal);
    field('close').addEventListener('click', close); field('cancel').addEventListener('click', close);
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
    field('hue').addEventListener('input', e => { h = Number(e.target.value); paint(); });
    const canvas = field('canvas');
    const pointer = e => { const rect = canvas.getBoundingClientRect(); s = clamp((e.clientX - rect.left) / rect.width); v = 1 - clamp((e.clientY - rect.top) / rect.height); paint(); };
    canvas.addEventListener('pointerdown', e => { canvas.setPointerCapture(e.pointerId); pointer(e); });
    canvas.addEventListener('pointermove', e => { if (canvas.hasPointerCapture(e.pointerId)) pointer(e); });
    canvas.addEventListener('keydown', e => {
      if (!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key)) return;
      e.preventDefault(); s = clamp(s + (e.key === 'ArrowRight' ? .01 : e.key === 'ArrowLeft' ? -.01 : 0));
      v = clamp(v + (e.key === 'ArrowUp' ? .01 : e.key === 'ArrowDown' ? -.01 : 0)); paint();
    });
    field('hex').addEventListener('input', e => {
      const values = parse(e.target.value);
      if (!values) { field('error').textContent = 'Enter a valid HEX code, such as #A34C78.'; field('apply').disabled = true; return; }
      [h, s, v] = hsv(values); paint(false); ['r','g','b'].forEach((key, i) => field(key).value = values[i]);
    });
    ['r','g','b'].forEach(key => field(key).addEventListener('input', () => {
      const values = ['r','g','b'].map(k => field(k).value);
      if (values.some(n => n === '' || !Number.isInteger(Number(n)) || Number(n) < 0 || Number(n) > 255)) {
        field('error').textContent = 'RGB values must be whole numbers from 0 to 255.'; field('apply').disabled = true; return;
      }
      [h, s, v] = hsv(values.map(Number)); paint();
    }));
    addVariantRow = (kind, prefill={}) => {
      const row = document.createElement('div'); row.className = 'product-color-nested-row';
      [...variantFields,'Stock','Price'].forEach(name=>{
        const label = document.createElement('label'); const caption=document.createElement('span');caption.textContent=name;label.append(caption);
        const input = document.createElement(name==='Size'?'select':'input'); input.dataset.optionField = name;
        if(name==='Size'){input.append(new Option('Select size',''));['XXS','XS','S','M','L','XL','XXL','3XL','4XL','One Size'].forEach(size=>input.append(new Option(size,size)));}
        if(name!=='Size') input.type = ['Stock','Price','Weight'].includes(name)?'number':'text';
        input.placeholder = name==='Dimensions'?'W x H cm':name==='Weight'?'e.g. 250 g':name;
        if(input.type==='number'){input.min=0;input.step=name==='Stock'?'1':'.01';}
        const saved = name==='Stock'?prefill.stock:name==='Price'?prefill.price:prefill.attributes?.[name];
        if(name==='Size'&&saved&&saved!=='Not applicable'&&![...input.options].some(option=>option.value===saved))input.append(new Option(saved,saved));
        input.value=saved==null||saved==='Not applicable'?'':name==='Weight'?(String(saved).match(/[\d.]+/)?.[0] || ''):saved;
        if(name==='Weight'){caption.textContent='Weight (g)';input.placeholder='Weight';input.min='0.01';input.dataset.weightUnit=String(saved || '').match(/\b(kg|g)\b/i)?.[1] || 'g';if(input.dataset.weightUnit==='kg')caption.textContent='Weight (kg)';}
        if(name==='Dimensions'){
          input.type='hidden';label.hidden=true;label.append(input);row.append(label);
          const values=String(saved || '').match(/\d+(?:\.\d+)?/g) || [];
          ['Width','Height'].forEach((dimension,index)=>{
            const dimensionLabel=document.createElement('label');const dimensionCaption=document.createElement('span');dimensionCaption.textContent=dimension+' (cm)';
            const dimensionInput=document.createElement('input');dimensionInput.type='number';dimensionInput.min='0.01';dimensionInput.step='.01';dimensionInput.inputMode='decimal';dimensionInput.placeholder=dimension;dimensionInput.dataset.optionField=dimension;dimensionInput.value=values[index] || '';
            dimensionInput.addEventListener('input',()=>{const width=row.querySelector('[data-option-field=Width]').value,height=row.querySelector('[data-option-field=Height]').value;input.value=width&&height?width+' x '+height+' cm':'';});
            dimensionLabel.append(dimensionCaption,dimensionInput);row.append(dimensionLabel);
          });
          return;
        }
        input.maxLength=100;label.append(input);row.append(label);

      });
      row.querySelectorAll('input[type=number]').forEach(input=>{
        input.inputMode='decimal';
        input.addEventListener('keydown',event=>{if(!event.ctrlKey&&!event.metaKey&&['e','E','+','-'].includes(event.key))event.preventDefault();});
        input.addEventListener('paste',event=>{if(!/^\d*(?:\.\d*)?$/.test(event.clipboardData.getData('text').trim()))event.preventDefault();});
      });
      const remove=document.createElement('button');remove.type='button';remove.className='product-remove-child';remove.innerHTML='&times;';remove.title='Remove option';remove.setAttribute('aria-label','Remove option');
      remove.addEventListener('click',()=>{row.remove();field('base').hidden=field('options').children.length>0;syncRequiredMeasurements();});row.append(remove);
      floatLabels(row);field('options').append(row);
      row.addEventListener('input',syncRequiredMeasurements);row.addEventListener('change',syncRequiredMeasurements);syncRequiredMeasurements();
      field('base').hidden=true;
    };
    field('add-row').addEventListener('click',()=>addVariantRow());

    field('apply').addEventListener('click', () => {
      const children=[];
      if(allowVariants){
        syncRequiredMeasurements();
        const rows=[...field('options').children];
        for(const [index,row] of rows.entries()){
          const missing=[...row.querySelectorAll('[data-option-field][required]')].filter(input=>!input.value.trim());
          if(missing.length){
            const names=missing.map(input=>input.closest('label').querySelector('.product-palette-caption').dataset.baseText);
            field('error').textContent='Option '+(index+1)+': complete '+names.join(', ')+'. Each option must include the measurements used in the first option.';
            missing.forEach(input=>input.setAttribute('aria-invalid','true'));missing[0].focus();return;
          }
        }
        for(const row of field('options').children){
          const read=name=>row.querySelector('[data-option-field="'+name+'"]').value.trim();
          const attributes=Object.fromEntries(variantFields.map(name=>[name,read(name)]));
          const width=read('Width'),height=read('Height');
          if(Boolean(width)!==Boolean(height)||[...row.querySelectorAll('input[type=number]')].some(input=>!input.checkValidity())){
            field('error').textContent='Enter positive numbers for weight, width and height. Complete both dimensions, or leave both blank.';return;
          }
          if(attributes.Weight)attributes.Weight+=' '+row.querySelector('[data-option-field=Weight]').dataset.weightUnit;
          if(!Object.values(attributes).some(Boolean))continue;
          if(read('Stock')===''||!Number.isInteger(Number(read('Stock')))||Number(read('Stock'))<0||(read('Price')!==''&&Number(read('Price'))<0)){
            field('error').textContent='Add a size, weight or dimensions and valid stock for each option.';return;
          }
          children.push({attributes,stock:Number(read('Stock')),price:read('Price')});
        }
      }
      const blank=allowVariants&&!children.length?field('options').firstElementChild:null;
      const stock=blank?blank.querySelector('[data-option-field=Stock]').value:field('stock').value;
      const price=blank?blank.querySelector('[data-option-field=Price]').value:field('price').value;
      const base={stock:stock===''?0:Number(stock),price};
      if(!children.length&&(!Number.isInteger(base.stock)||base.stock<0||(base.price!==''&&(!Number.isFinite(Number(base.price))||Number(base.price)<0)))){field('error').textContent='Enter valid stock and price.';return;}
      if (onApply) onApply(hex(rgb(h, s, v)), field('name').value.trim(), children, base);
      if (target?.isConnected) { target.value = hex(rgb(h, s, v)); target.dispatchEvent(new Event('input', {bubbles:true})); }
      close();
    });
    modal.addEventListener('keydown', e => {
      if (e.key === 'Escape') { e.stopPropagation(); close(); }
      if (e.key === 'Tab') {
        const controls = [...modal.querySelectorAll('button,input,select,[tabindex="0"]')].filter(el => !el.disabled && el.getClientRects().length);
        if (e.shiftKey && document.activeElement === controls[0]) { e.preventDefault(); controls.at(-1).focus(); }
        else if (!e.shiftKey && document.activeElement === controls.at(-1)) { e.preventDefault(); controls[0].focus(); }
      }
    });
  }
  window.openProductColorPicker = options => {
    if (!modal) create();
    allowVariants=!!options.allowVariants; field('variants').hidden=!allowVariants; field('variants').open=false;field('options').replaceChildren();
    modal.classList.toggle('is-size-editor',!!options.sizeOnly);
    modal.querySelector('#productColorTitle').textContent=options.sizeOnly?'Customize sizes':'Customize color';
    modal.querySelector('.modal-head p').textContent=options.sizeOnly?'Set stock, price and measurements for each selected size.':'Choose a shade or enter an exact color code.';
    target = options.input || null; onApply = options.onApply || null; trigger = options.trigger || target;
    original = options.color || target?.value || '#000000';
    [h, s, v] = hsv(parse(original) || [0,0,0]); field('original').style.background = original;
    field('name').value = options.name || ''; field('name').parentElement.hidden = !onApply;
    variantFields=[...new Set(['Size','Weight','Dimensions',...(options.extraFields || [])])];
    field('stock').value=options.stock ?? 0;field('price').value=options.price ?? '';field('base').hidden=!onApply;
    (options.children || []).forEach(child=>addVariantRow(variantFields.find(name=>child.attributes?.[name]&&child.attributes[name]!=='Not applicable')||'Size',child));
    if(allowVariants && !field('options').children.length)addVariantRow(null,{stock:options.stock ?? 0,price:options.price ?? ''});
    field('add-row').hidden=!!options.sizeOnly;
    if(options.sizeOnly)field('options').querySelectorAll('[data-option-field=Size]').forEach(input=>input.disabled=true);
    if(allowVariants)syncRequiredMeasurements();
    field('apply').textContent=options.sizeOnly?'Save sizes':options.editing?'Save color':'Use color';
    paint(); modal.classList.add('open'); (options.sizeOnly?field('options').querySelector('[data-option-field=Stock]'):field('hex')).focus();
  };
  document.addEventListener('click', e => {
    const input = e.target.closest('input.opt-color'); if (!input) return;
    e.preventDefault(); window.openProductColorPicker({input});
  });
})();
