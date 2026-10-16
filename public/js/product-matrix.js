(() => {
  const source = document.getElementById('matrixProductData'); if (!source) return;
  const data = JSON.parse(source.textContent), choices = data.axes.map(() => null);
  const selected = () => choices.every(Boolean) ? data.rows.find(row => row.option_ids.every((id, index) => choices[index] === id)) : null;
  function refresh() {
    document.querySelectorAll('[data-matrix-option]').forEach(button => {
      const index = Number(button.dataset.matrixAxis), id = button.dataset.matrixOption;
      button.disabled = !data.rows.some(row => Number(row.stock) > 0 && row.option_ids[index] === id && choices.every((choice, other) => other === index || !choice || row.option_ids[other] === choice));
      button.classList.toggle('active', choices[index] === id);
    });
    const row = selected();
    document.getElementById('matrixStock').textContent = row ? `Stock: ${row.stock} available` : 'Please select a variation.';
    if (typeof updatePurchaseAvailability === 'function') updatePurchaseAvailability(row ? Number(row.stock) : data.rows.reduce((total,row) => total + Number(row.stock), 0));
    if (typeof updatePriceDisplay === 'function') updatePriceDisplay();
  }
  function choose(index, id) {
    choices[index] = id;
    if (choices.every(Boolean) && !data.rows.some(row => Number(row.stock) > 0 && row.option_ids.every((value, i) => choices[i] === value))) {
      choices.forEach((_, other) => { if (other !== index) choices[other] = null; });
    }
    const option = data.axes[index].options.find(option => option.id === id);
    if (option?.image && typeof showProductImage === 'function') showProductImage(option.image);
    refresh();
  }
  window.productMatrix = { selected, totalStock: () => data.rows.reduce((total,row) => total + Number(row.stock), 0),
    selectImage: src => {
      for (let i = 0; i < data.axes.length; i++) { const option = data.axes[i].options.find(option => option.image === src); if (option && choices[i] !== option.id) { choose(i, option.id); return; } }
    }, range: () => {
      const prices = data.rows.map(row => Number(row.price)), min = Math.min(...prices), max = Math.max(...prices);
      const format = value => value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      return min === max ? `₱${format(min)}` : `₱${format(min)} – ₱${format(max)}`;
    } };
  document.querySelectorAll('[data-matrix-option]').forEach(button => button.addEventListener('click', () => choose(Number(button.dataset.matrixAxis), button.dataset.matrixOption)));
  refresh();
})();
