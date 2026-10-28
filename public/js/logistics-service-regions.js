const serviceRegionSelect = document.getElementById('hub_region_input');
let serviceRegionRequest = 0;
setLoading(hubProvinceSelect, 'Select service region first');
fetchJSON(`${PSGC}/regions/`)
    .then(data => populateSelect(serviceRegionSelect, data, 'code', 'name', 'Select service region'))
    .catch(() => setLoading(serviceRegionSelect, 'Unable to load regions. Refresh to retry.'));

serviceRegionSelect.addEventListener('change', async function () {
    const requestId = ++serviceRegionRequest;
    ++hubMuniRequestId;
    hubMuniPanel.style.display = 'none';
    hubMuniBtn.disabled = true;
    hubMuniCheckboxes.replaceChildren();
    hubMuniSelectAll.checked = false;
    hubMuniLabel.textContent = 'Select province first';
    setLoading(hubProvinceSelect, 'Loading provinces…');
    const code = this.selectedOptions[0].dataset.code;
    try {
        const provinces = (await loadProvinces()).filter(item => String(item.regionCode) === String(code));
        if (requestId !== serviceRegionRequest) return;
        if (provinces.length) {
            populateSelect(hubProvinceSelect, provinces, 'code', 'name', 'Select province');
        } else {
            const option = new Option(this.value, this.value, true, true);
            option.dataset.code = code;
            option.dataset.isRegion = '1';
            hubProvinceSelect.replaceChildren(option);
            hubProvinceSelect.disabled = false;
            hubProvinceSelect.dispatchEvent(new Event('change'));
        }
    } catch {
        if (requestId === serviceRegionRequest) setLoading(hubProvinceSelect, 'Unable to load provinces. Select the region again.');
    }
});
