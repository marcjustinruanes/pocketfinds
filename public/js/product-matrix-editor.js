window.ProductMatrixEditor = class {
  constructor(form, options = {}) {
    this.form = form; this.root = form.querySelector('[data-role=matrix-editor]');
    this.axes = structuredClone(options.variantAxes || []); this.rows = structuredClone(options.variantRows || []);
    this.removed = new Set(); this.files = new Map();
    this.legacy = form.querySelector('[data-role=legacy-variations]');
    this.stock = form.querySelector('[data-role=no-variation-stock]');
    this.stockParent = this.stock.parentElement;
    this.toggle = this.root.querySelector('[data-role=matrix-mode]');
    this.toggle.checked = this.axes.length > 0 || !(options.existingVariations || []).length;
    this.root.querySelector('[data-role=matrix-switch-label]').hidden = !(options.existingVariations || []).length;
    const oldKeys = new Set(this.rows.map(row => row.option_ids.join('|')));
    if (this.axes.length) this.combinations().forEach(ids => { if (!oldKeys.has(ids.join('|'))) this.removed.add(ids.join('|')); });
    this.toggle.addEventListener('change', () => this.render());
    this.root.querySelector('[data-role=matrix-add-axis]').addEventListener('click', () => {
      if (this.axes.length >= 2) return this.message('A maximum of 2 variation types is allowed.');
      this.axes.push({ id: crypto.randomUUID(), name: '', options: [] }); this.render();
    });
    this.render();
  }
  message(text) { const box = this.root.querySelector('[data-role=matrix-message]'); box.textContent = text; box.hidden = !text; }
  combinations() { return this.axes.reduce((rows, axis) => rows.flatMap(row => axis.options.map(option => [...row, option.id])), [[]]); }
  input(label, value, path, change, type = 'text') {
    const wrap = document.createElement('label'); wrap.textContent = label; wrap.style.cssText = 'display:flex;flex-direction:column;gap:4px';
    const field = document.createElement('input'); field.type = type; field.value = value ?? ''; field.required = true; field.dataset.matrixField = path;
    field.setAttribute('aria-label', label); field.addEventListener('input', () => { field.parentElement.querySelector('[data-matrix-error]')?.remove(); field.removeAttribute('aria-invalid'); change(field.value); this.sync(); });
    field.addEventListener('invalid', () => this.fieldError(field, field.validationMessage));
    wrap.append(field); return { wrap, field };
  }
  fieldError(field, message) {
    field.setAttribute('aria-invalid', 'true'); let error = field.parentElement.querySelector('[data-matrix-error]');
    if (!error) { error = document.createElement('span'); error.dataset.matrixError = ''; error.setAttribute('role', 'alert'); field.parentElement.append(error); }
    error.textContent = message;
  }
  showErrors(errors) {
    this.root.querySelectorAll('[data-matrix-error]').forEach(error => error.remove());
    this.root.querySelectorAll('[data-matrix-field]').forEach(field => field.removeAttribute('aria-invalid'));
    for (const [path, messages] of Object.entries(errors || {})) {
      const field = [...this.root.querySelectorAll('[data-matrix-field]')].find(field => field.dataset.matrixField === path);
      if (field) this.fieldError(field, messages.join(' '));
      else if (path.startsWith('variant_') || path === 'matrix_images') this.message(messages.join(' '));
    }
  }
  render() {
    const on = this.toggle.checked;
    this.root.querySelector('[data-role=matrix-panel]').hidden = !on; this.legacy.hidden = on;
    if (on) this.root.querySelector('[data-role=matrix-stock]').append(this.stock); else this.stockParent.append(this.stock);
    const restock = this.form.querySelector('[name=restock_date]')?.closest('.form-row');
    if (restock) this.root.append(restock);
    const container = this.root.querySelector('[data-role=matrix-axes]'); container.replaceChildren(); this.message('');
    this.axes.forEach((axis, i) => {
      const group = document.createElement('fieldset'); group.dataset.matrixAxis = axis.id;
      const heading = this.input(`Variation ${i + 1} name`, axis.name, `variant_axes.${i}.name`, value => { axis.name = value; this.renderRows(); }); group.append(heading.wrap);
      const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Remove variation';
      remove.addEventListener('click', () => { this.axes.splice(i, 1); this.render(); }); group.append(remove);
      axis.options.forEach((option, j) => {
        const row = document.createElement('div'); row.style.cssText = 'display:flex;gap:8px;align-items:start;margin:8px 0';
        const control = this.input(`${axis.name || 'Variation'} option ${j + 1}`, option.value, `variant_axes.${i}.options.${j}.value`, value => { option.value = value; this.renderRows(); });
        row.append(control.wrap);
        const photo = document.createElement('input'); photo.type = 'file'; photo.accept = 'image/jpeg,image/png'; photo.name = `matrix_images[${option.id}]`; photo.setAttribute('aria-label', `Image for ${option.value || 'option'}`); photo.style.maxWidth = '150px';
        if (this.files.has(option.id)) { const dt = new DataTransfer(); dt.items.add(this.files.get(option.id)); photo.files = dt.files; }
        photo.addEventListener('change', () => {
          if (photo.files[0] && (!['image/jpeg','image/png'].includes(photo.files[0].type) || photo.files[0].size > 5 * 1024 * 1024)) { this.message('Option images must be JPG or PNG, up to 5 MB.'); photo.value = ''; return; }
          if (photo.files[0]) this.files.set(option.id, photo.files[0]);
        }); row.append(photo);
        const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Remove option';
        remove.addEventListener('click', () => { axis.options.splice(j,1); this.files.delete(option.id); this.render(); }); row.append(remove); group.append(row);
      });
      const add = document.createElement('button'); add.type = 'button'; add.textContent = 'Add option'; add.dataset.addMatrixOption = axis.id;
      add.addEventListener('click', () => { if (axis.options.length >= 20) return this.message('A maximum of 20 options per variation is allowed.'); axis.options.push({ id: crypto.randomUUID(), value: '', image: null }); this.render(); });
      group.append(add); container.append(group);
    });
    this.renderRows();
  }
  renderRows() {
    const previous = new Map(this.rows.map(row => [row.option_ids.join('|'), row]));
    this.rows = this.axes.length ? this.combinations().filter(ids => !this.removed.has(ids.join('|'))).map(ids => previous.get(ids.join('|')) || { id: crypto.randomUUID(), option_ids: ids, price: '', stock: 0, sku: '' }) : [];
    const body = this.root.querySelector('[data-role=matrix-row-body]'); body.replaceChildren();
    this.rows.forEach((row, i) => {
      row.label = this.axes.map((axis, index) => axis.options.find(option => option.id === row.option_ids[index])?.value || '?').join('-');
      const tr = document.createElement('tr'); tr.dataset.matrixRow = row.id; const label = document.createElement('th'); label.textContent = row.label; tr.append(label);
      for (const field of ['price','stock','sku']) {
        const td = document.createElement('td'); const control = this.input(`${row.label} ${field}`, row[field], `variant_rows.${i}.${field}`, value => { row[field] = value; }, field === 'sku' ? 'text' : 'number');
        if (field === 'price') { control.field.min = '1'; control.field.max = '1000000'; control.field.step = '0.01'; }
        if (field === 'stock') { control.field.min = '0'; control.field.max = '99999'; control.field.step = '1'; }
        if (field === 'sku') control.field.maxLength = 100;
        td.append(control.wrap); tr.append(td);
      }
      const td = document.createElement('td'), remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Delete variant';
      remove.addEventListener('click', () => { this.removed.add(row.option_ids.join('|')); this.renderRows(); }); td.append(remove); tr.append(td); body.append(tr);
    });
    this.root.querySelector('[data-role=matrix-table]').hidden = !this.rows.length; this.sync();
  }
  sync() {
    const on = this.toggle.checked, matrix = on && this.axes.length > 0;
    const axesInput = this.root.querySelector('[name=variant_axes]'), rowsInput = this.root.querySelector('[name=variant_rows]');
    axesInput.disabled = rowsInput.disabled = !on;
    axesInput.value = JSON.stringify(this.axes); rowsInput.value = JSON.stringify(this.rows);
    this.stock.hidden = matrix; this.form.elements.namedItem('price').readOnly = !!matrix;
    this.form.elements.namedItem('stock').readOnly = !!matrix;
    if (matrix) {
      const prices = this.rows.map(row => Number(row.price)).filter(price => price >= 1);
      this.form.elements.namedItem('price').value = prices.length ? Math.min(...prices) : '';
      this.form.elements.namedItem('stock').value = this.rows.reduce((total,row) => total + (Number(row.stock) || 0), 0);
    }
    this.root.querySelectorAll('[data-role=matrix-panel] input').forEach(field => { field.disabled = !on; });
    this.axes.forEach((axis, i) => {
      const seen = new Set(); axis.options.forEach((option,j) => {
        const field = this.root.querySelector(`[data-matrix-field="variant_axes.${i}.options.${j}.value"]`), key = option.value.trim().toLowerCase();
        field?.setCustomValidity(key && seen.has(key) ? 'Option names must be distinct, ignoring case.' : ''); seen.add(key);
      });
    });
    this.rows.forEach((row, i) => {
      const field = this.root.querySelector(`[data-matrix-field="variant_rows.${i}.sku"]`);
      const previous = this.rows.slice(0,i).some(other => other.sku.trim().toLowerCase() === row.sku.trim().toLowerCase());
      field?.setCustomValidity(row.sku.trim() && previous ? 'Every variant requires a unique SKU.' : '');
    });
  }
};
