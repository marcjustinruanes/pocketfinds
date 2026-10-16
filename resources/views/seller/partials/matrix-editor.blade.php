<section data-role="matrix-editor">
  <label data-role="matrix-switch-label"><input type="checkbox" data-role="matrix-mode"> Use combination editor</label>
  <div data-role="matrix-panel">
    <h4>Variations</h4><p>Up to 2 types and 20 options per type. Each combination has its own price, stock and SKU.</p>
    <div data-role="matrix-stock"></div>
    <div data-role="matrix-axes"></div>
    <button type="button" class="btn btn-outline" data-role="matrix-add-axis">Add variation</button>
    <p data-role="matrix-message" role="alert" hidden></p>
    <div style="overflow:auto" data-role="matrix-table" hidden><table class="tbl"><thead><tr><th>Combination</th><th>Price</th><th>Stock</th><th>SKU</th><th>Actions</th></tr></thead><tbody data-role="matrix-row-body"></tbody></table></div>
  </div>
  <input type="hidden" name="variant_axes"><input type="hidden" name="variant_rows">
</section>
