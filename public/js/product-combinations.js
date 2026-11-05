(function () {
  'use strict';

  // Also exported for the focused selection tests.
  function resolveCombination(group, selections) {
    if (group.requires_stock_setup) return null;
    if (!group.axes.every(axis => Object.hasOwn(selections, axis.name))) return null;
    return group.options.find(option => group.axes.every(axis =>
      option.attributes[axis.name] === selections[axis.name])) || null;
  }

  if (typeof module !== 'undefined') module.exports = { resolveCombination };
  if (typeof document === 'undefined') return;
  const data = document.getElementById('pdCombinationData');
  if (!data) return;
  const group = JSON.parse(data.textContent);
  const selections = {};
  const tab = document.getElementById('tab-options');

  window.productCombinations = {
    selected() {
      const option = resolveCombination(group, selections);
      if (!option) return null;
      return { dataset: {
        group: group.name, value: option.value, stock: String(option.stock),
        price: String(option.price ?? document.getElementById('pdPrice').dataset.basePrice),
        image: option.image || '', exclusive: 'true',
      }, disabled: Number(option.stock) <= 0 };
    },
    select(button) {
      if (button.disabled) return;
      selections[button.dataset.group] = button.dataset.value;
      button.closest('.pd-opt-row').querySelectorAll('.pd-opt-btn').forEach(option => {
        const active = option === button;
        option.classList.toggle('active', active);
        option.setAttribute('aria-pressed', String(active));
      });
      const selected = this.selected();
      const image = selected?.dataset.image || button.dataset.image;
      if (image) {
        window.showProductImage(image);
        window.syncThumbForImage(image);
      }
      window.updatePriceDisplay();
      window.changeQty(0);
    },
    selectImage(src) {
      const colorAxis = group.axes.find(axis => /^colou?rs?$/i.test(axis.name));
      const matches = [...tab.querySelectorAll('.pd-opt-btn[data-image]')].filter(option =>
        option.dataset.image === src && !option.disabled);
      const button = matches.find(option => option.dataset.group === colorAxis?.name) || matches[0];
      if (button && !button.disabled) this.select(button);
    },
  };
})();
