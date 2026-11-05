(function (root) {
  'use strict';
  const key = attributes => JSON.stringify(Object.entries(attributes).sort(([a], [b]) => a.localeCompare(b)));
  function rows(axes) {
    if (axes.some(axis => !axis.options.length)) return [];
    if (axes.reduce((total, axis) => total * axis.options.length, 1) > 500) {
      throw new Error('Use at most 500 combinations per product.');
    }
    let combinations = [{}];
    axes.forEach(axis => {
      combinations = combinations.flatMap(attributes => axis.options.map(option => ({ ...attributes, [axis.name]: option.value })));
    });
    return combinations.map(attributes => ({
      key: key(attributes), attributes, value: axes.map(axis => attributes[axis.name]).join(' / '),
    }));
  }
  const usesCombinations = axes => axes.length > 1;
  const api = { key, rows, usesCombinations };
  if (typeof module !== 'undefined') module.exports = api;
  else root.ProductVariantMatrix = api;
})(typeof window === 'undefined' ? globalThis : window);
