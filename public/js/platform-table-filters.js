(() => {
  document.querySelectorAll('[data-platform-filter-table]').forEach(table => {
    const controls = [...document.querySelectorAll('[data-platform-filter]')].filter(control => control.dataset.filterTable === table.id);
    const search = [...document.querySelectorAll('[data-table-search]')].find(input => input.dataset.tableSearch === table.id);
    let status = '';
    const apply = () => {
      const query = (search?.value || '').trim().toLowerCase();
      table.querySelectorAll('tbody tr[data-account-type], tbody tr[data-status], tbody tr[data-kind]').forEach(row => {
        const matchesType = controls.every(control => control.value === 'all' || (control.value === 'outofstock' && control.dataset.platformFilter === 'status' ? row.dataset.stock === 'out' : row.dataset[control.dataset.platformFilter] === control.value));
        const matchesStatus = !status || row.dataset.status === status;
        const matchesSearch = !query || `${row.textContent} ${row.dataset.search || ''}`.toLowerCase().includes(query);
        row.hidden = !(matchesType && matchesStatus && matchesSearch);
      });
    };
    controls.forEach(control => control.addEventListener('change', () => {
      control.closest('.platform-column-filter').classList.toggle('is-filtered', control.value !== 'all');
      apply();
    }));
    search?.addEventListener('input', apply);
    if (table.id === 'updateRequestsTable') {
      const buttons = [...document.querySelectorAll('[data-status-kpi]')];
      buttons.forEach(button => button.addEventListener('click', () => {
        status = button.dataset.statusKpi;
        buttons.forEach(other => { other.classList.toggle('active', other === button); other.setAttribute('aria-pressed', String(other === button)); });
        apply();
      }));
      buttons.find(button => !button.dataset.statusKpi)?.classList.add('active');
    }
    apply();
  });
})();
