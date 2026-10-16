document.addEventListener('DOMContentLoaded', () => {
  const byId = id => document.getElementById(id);
  const form = byId('addAddressForm');
  if (!form) return;
  const modal = byId('addAddressModal'), removeModal = byId('removeAddressModal');
  const region = byId('addrRegion'), province = byId('addrProvince'), city = byId('addrMunicipality'), barangay = byId('addrBarangay');
  const error = byId('addAddressError'), save = byId('saveAddressBtn');
  const api = '/locations';
  let editing = null, removing = null, returnFocus = null, revision = 0;
  const fields = { label: 'addrLabel', recipient_name: 'addrRecipientName', contact_no: 'addrContactNo',
    region: 'addrRegion', province: 'addrProvince', municipality: 'addrMunicipality', barangay: 'addrBarangay', house_no: 'addrHouseNo', street: 'addrStreet' };
  const headers = () => ({ Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' });
  function reset(select, message) {
    select.replaceChildren(new Option(message, ''));
    select.disabled = true;
  }
  function populate(select, places, label, selected = '') {
    reset(select, label);
    [...places].sort((a, b) => a.name.localeCompare(b.name)).forEach(place => {
      const option = new Option(place.name, place.name);
      option.dataset.code = place.code;
      select.add(option);
    });
    // Keep legacy address text available when a geographic name has changed.
    if (selected && ![...select.options].some(option => option.value === selected)) select.add(new Option(selected, selected));
    select.value = selected;
    select.disabled = false;
  }
  const code = select => select.selectedOptions[0]?.dataset.code;
  async function places(path) {
    const response = await fetch(`${api}/${path}/`);
    if (!response.ok) throw new Error('Could not load locations. Please retry.');
    return response.json();
  }
  function showError(message) { error.textContent = message; error.style.display = 'block'; }
  async function loadRegions() {
    const stamp = ++revision;
    delete province.dataset.directRegion;
    reset(region, 'Loading regions…');
    reset(province, 'Select region first'); reset(city, 'Select region first'); reset(barangay, 'Select city first');
    try {
      const data = await places('regions');
      if (stamp !== revision) return;
      populate(region, data, 'Select region');
      byId('retryAddressLocations').hidden = true;
    } catch (failure) { showError(failure.message); byId('retryAddressLocations').hidden = false; }
  }
  async function loadRegionChildren(selected = {}) {
    const stamp = ++revision, selectedCode = code(region);
    reset(province, 'Loading provinces…'); reset(city, 'Select province first'); reset(barangay, 'Select city first');
    if (!selectedCode) return;
    try {
      const provinces = await places(`regions/${selectedCode}/provinces`);
      if (stamp !== revision) return;
      if (provinces.length) {
        populate(province, provinces, 'Select province', selected.province);
        if (selected.province) await loadCities(selected);
      } else {
        // NCR has cities directly under the region, without a province.
        populate(province, [{ name: 'Metro Manila', code: selectedCode }], 'Select province', 'Metro Manila');
        province.dataset.directRegion = selectedCode;
        const cities = await places(`regions/${selectedCode}/cities-municipalities`);
        if (stamp !== revision) return;
        populate(city, cities, 'Select city / municipality', selected.municipality);
        if (selected.municipality) await loadBarangays(selected.barangay);
      }
    } catch (failure) { if (stamp === revision) showError(failure.message); }
  }
  async function loadCities(selected = {}) {
    const stamp = ++revision, selectedCode = code(province);
    reset(city, 'Loading cities…'); reset(barangay, 'Select city first');
    if (!selectedCode) return;
    try {
      const path = province.dataset.directRegion ? `regions/${province.dataset.directRegion}` : `provinces/${selectedCode}`;
      const data = await places(`${path}/cities-municipalities`);
      if (stamp !== revision) return;
      populate(city, data, 'Select city / municipality', selected.municipality);
      if (selected.municipality) await loadBarangays(selected.barangay);
    } catch (failure) { if (stamp === revision) showError(failure.message); }
  }
  async function loadBarangays(selected = '') {
    const stamp = ++revision, selectedCode = code(city);
    reset(barangay, 'Loading barangays…');
    if (!selectedCode) return;
    try {
      const data = await places(`cities-municipalities/${selectedCode}/barangays`);
      if (stamp === revision) populate(barangay, data, 'Select barangay', selected);
    } catch (failure) { if (stamp === revision) showError(failure.message); }
  }
  region.addEventListener('change', () => { delete province.dataset.directRegion; loadRegionChildren(); });
  province.addEventListener('change', () => loadCities());
  city.addEventListener('change', () => loadBarangays());
  byId('retryAddressLocations').addEventListener('click', loadRegions);
  function open(target) { target.classList.add('open'); target.setAttribute('aria-hidden', 'false'); }
  function close(target) { target.classList.remove('open'); target.setAttribute('aria-hidden', 'true'); returnFocus?.focus(); }
  document.addEventListener('click', async event => {
    const add = event.target.closest('[data-modal-open="addAddressModal"]');
    const edit = event.target.closest('[data-edit-address]');
    const remove = event.target.closest('[data-delete-address]');
    if (add || edit) {
      event.preventDefault();
      returnFocus = add || edit; editing = edit?.dataset.editAddress || null;
      form.reset(); error.style.display = 'none'; save.disabled = true;
      byId('addAddressTitle').textContent = editing ? 'Edit delivery address' : 'Add delivery address';
      open(modal);
      try {
        await loadRegions();
        if (editing) {
          const response = await fetch(`${form.dataset.storeUrl}/${editing}`, { headers: headers() });
          if (!response.ok) throw new Error('Unable to open this address.');
          const { address } = await response.json();
          for (const [field, id] of Object.entries(fields)) if (!['region', 'province', 'municipality', 'barangay'].includes(field)) byId(id).value = address[field] || '';
          if (address.region) {
            region.value = address.region;
            await loadRegionChildren(address);
          }
        }
      } catch (failure) { showError(failure.message); }
      finally { save.disabled = false; byId('addrRecipientName').focus(); }
    }
    if (remove) {
      event.preventDefault(); returnFocus = remove; removing = remove.dataset.deleteAddress;
      byId('removeAddressError').hidden = true;
      open(removeModal); byId('keepAddressBtn').focus();
    }
    const target = event.target.closest('.address-modal');
    if (target && (event.target === target || event.target.closest('[data-modal-close]'))) close(target);
  });
  [modal, removeModal].forEach(target => target.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(target); }
    if (event.key !== 'Tab') return;
    const controls = [...target.querySelectorAll('button,input,select')].filter(el => !el.disabled && el.getClientRects().length);
    const first = controls[0], last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
  }));
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (save.disabled) return;
    error.style.display = 'none';
    const payload = Object.fromEntries(Object.entries(fields).map(([key, id]) => [key, byId(id).value.trim()]));
    save.disabled = true; save.textContent = 'Saving…';
    try {
      const response = await fetch(form.dataset.storeUrl + (editing ? `/${editing}` : ''), {
        method: editing ? 'PATCH' : 'POST', headers: headers(), body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Could not save this address.');
      window.location.reload();
    } catch (failure) { showError(failure.message); }
    finally { save.disabled = false; save.textContent = 'Save address'; }
  });
  byId('confirmRemoveAddressBtn').addEventListener('click', async event => {
    const button = event.currentTarget, message = byId('removeAddressError');
    if (!removing || button.disabled) return;
    button.disabled = true;
    try {
      const response = await fetch(`${form.dataset.storeUrl}/${removing}`, { method: 'DELETE', headers: headers() });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Could not remove this address.');
      window.location.reload();
    } catch (failure) { message.textContent = failure.message; message.hidden = false; button.disabled = false; }
  });
});
