(function () {
  'use strict';

  function quantityState(current, stock, ready, delta = 0) {
    const available = Number.isFinite(Number(stock)) ? Math.max(0, Math.floor(Number(stock))) : 0;
    const max = ready ? Math.min(99, available) : 0;
    const value = Math.max(1, Math.min(Math.max(1, max), (Number(current) || 1) + delta));
    return { value, max, decreaseDisabled: max === 0 || value <= 1, increaseDisabled: max === 0 || value >= max };
  }

  if (typeof module !== 'undefined') module.exports = { quantityState };
  if (typeof document === 'undefined') return;

  window.productQuantity = {
    sync(current, delta = 0) {
      const price = document.getElementById('pdPrice');
      const selected = window.selectedPurchaseOption();
      const requiresSelection = !!document.getElementById('pdSelectedStock');
      const ready = !requiresSelection || !!selected;
      const onVacation = price.dataset.onVacation === 'true';
      const stock = selected ? selected.dataset.stock : (requiresSelection ? 0 : price.dataset.baseStock);
      const state = quantityState(current, stock, ready && !onVacation, delta);
      const number = document.getElementById('pdQty');
      number.textContent = state.value;
      number.setAttribute('aria-label', 'Quantity: ' + state.value);
      document.getElementById('pdQtyDecrease').disabled = state.decreaseDisabled;
      document.getElementById('pdQtyIncrease').disabled = state.increaseDisabled;
      for (const id of ['pdAddCartBtn']) {
        const button = document.getElementById(id);
        if (!button) continue;
        const soldOut = ready ? Number(stock) <= 0 : Number(price.dataset.baseStock) <= 0;
        button.disabled = onVacation || soldOut;
        if (onVacation) continue;
        const label = soldOut ? 'Out of Stock' : 'Add to Cart';
        button.lastChild.textContent = ' ' + label;
        button.setAttribute('aria-label', label);
        button.title = label;
      }
      return state.value;
    },
  };
  window.changeQty(0);
})();
