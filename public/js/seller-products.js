(() => {
  const workspace = document.querySelector('[data-product-workspace]');
  if (!workspace) return;
  const search = workspace.querySelector('#productSearch');
  const category = workspace.querySelector('#shopCategoryFilter');
  const table = workspace.querySelector('#productTable');
  const rows = [...workspace.querySelectorAll('[data-product-row]')];
  const statusFilter = workspace.querySelector('#productStatusFilter');
  const counter = workspace.querySelector('[data-product-result-count]');
  const empty = workspace.querySelector('[data-product-empty]');
  const clear = workspace.querySelector('.product-list-meta [data-product-reset]');


  function refresh() {
    const status = statusFilter.value;
    const query = search.value.trim().toLocaleLowerCase();
    const filtered = query || status !== 'all' || (category && category.value !== 'all');
    let visible = 0;
    const ordered = [...rows].sort((a, b) => {
      return Number(b.dataset.updated) - Number(a.dataset.updated);
    });
    ordered.forEach(row => {
      const assigned = (row.dataset.shopCategories || '').split(',').filter(Boolean);
      const matchesCategory = !category || category.value === 'all' || (category.value === 'uncategorized' ? !assigned.length : assigned.includes(category.value));
      const matches = matchesCategory && (!query || row.dataset.search.includes(query)) && (status === 'all' || row.dataset.status === status);
      row.hidden = !matches;
      if (matches) visible++;
      table.tBodies[0].append(row);
    });
    table.hidden = false;
    empty.hidden = visible !== 0;
    clear.hidden = !filtered;
    counter.textContent = filtered ? `Showing ${visible} of ${rows.length} products` : `${rows.length} ${rows.length === 1 ? 'product' : 'products'}`;
  }
  search.addEventListener('input', refresh);
  if (category) category.addEventListener('change', refresh);
  statusFilter.addEventListener('change', refresh);
  workspace.querySelectorAll('[data-product-reset]').forEach(button => button.addEventListener('click', () => {
    if (category) category.value = 'all';
    search.value = ''; statusFilter.value = 'all'; refresh(); search.focus();
  }));
  workspace.querySelectorAll('[data-product-remove]').forEach(button => button.addEventListener('click', () => {
    window.confirmRemoveProduct(button.dataset.productRemove, button.dataset.productName);
  }));
  refresh();
})();

(() => {
 const modal = document.getElementById('discardProductChangesModal');
 if (!modal) return;
 let discard, returnFocus;
 window.confirmDiscardProductEditing = (callback, trigger) => {
  discard = callback; returnFocus = trigger;
  modal.classList.add('open');
  modal.querySelector('[data-keep-product-editing]').focus();
 };
 const keepEditing = () => { modal.classList.remove('open'); discard = null; returnFocus?.focus(); };
 modal.querySelector('[data-keep-product-editing]').addEventListener('click', keepEditing);
 modal.querySelector('[data-confirm-product-discard]').addEventListener('click', () => {
  const callback = discard; discard = null;
  modal.classList.remove('open'); callback?.();
  document.querySelector('.product-details-modal.open .product-details-edit')?.focus();
 });
 modal.addEventListener('click', event => { if (event.target === modal) keepEditing(); });
 modal.addEventListener('keydown', event => {
  if (event.key === 'Escape') { event.stopPropagation(); keepEditing(); }
  if (event.key === 'Tab') {
   const buttons = [...modal.querySelectorAll('button')];
   if (event.shiftKey && document.activeElement === buttons[0]) { event.preventDefault(); buttons.at(-1).focus(); }
   else if (!event.shiftKey && document.activeElement === buttons.at(-1)) { event.preventDefault(); buttons[0].focus(); }
  }
 });
})();
