(() => {
  'use strict';
  const page = document.getElementById('select-payroll-page');
  const rows = [...document.querySelectorAll('.payroll-row-checkbox')];
  if (!page) return;
  const count = document.getElementById('payroll-selection-count');
  const update = () => {
    const selected = rows.filter(row => row.checked).length;
    page.checked = rows.length > 0 && selected === rows.length;
    page.indeterminate = selected > 0 && selected < rows.length;
    page.disabled = rows.length === 0;
    if (count) count.textContent = `${selected} Staff selected on this page (maximum 25).`;
  };
  page.addEventListener('change', () => { rows.forEach(row => { row.checked = page.checked; }); update(); });
  rows.forEach(row => row.addEventListener('change', update));
  window.addEventListener('pageshow', update);
  update();
})();
