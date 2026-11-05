function renderHubOptions() {
    const hubSection = document.getElementById('hubSection');
    const container  = document.getElementById('hubSelectionContainer');
    const noticeEl   = document.getElementById('hubNoticeContainer');
    const selectedCompany = document.querySelector('input[name="business_name"]:checked')?.value;
    const province     = (document.getElementById('province')?.value || '').trim();
    const municipality = (document.getElementById('municipality')?.value || '').trim();

    document.getElementById('hubSelectError').style.display = 'none';

    if (!selectedCompany) {
        hubSection.style.display = 'none';
        container.innerHTML = '';
        if (noticeEl) noticeEl.style.display = 'none';
        currentSelectedHubId = null;
        document.getElementById('selected_hub_id').value = '';
        return;
    }

    hubSection.style.display = 'block';
    container.innerHTML = '';

    const allHubs = COMPANY_HUBS[selectedCompany] || [];
    activeProvHubs = allHubs.filter(h => h.province.toLowerCase() === province.toLowerCase());

    if (activeProvHubs.length === 0) {
        if (noticeEl) noticeEl.style.display = 'none';
        container.innerHTML = `<div style="padding:12px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;color:#dc2626;font-size:12px">
            <strong>${selectedCompany}</strong> does not have any hubs registered in ${province}. Please choose another company.
        </div>`;
        currentSelectedHubId = null;
        document.getElementById('selected_hub_id').value = '';
        return;
    }

    if (currentSelectedHubId && !activeProvHubs.some(h => h.id === currentSelectedHubId)) {
        currentSelectedHubId = null;
        document.getElementById('selected_hub_id').value = '';
    }

    const directMatch = activeProvHubs.find(h => h.municipality.toLowerCase() === municipality.toLowerCase());

    // When the rider's own municipality really does have a hub here, and it's hiring,
    // surface it as the pre-picked choice right away instead of making them dig for
    // it in the "browse all hubs" modal — they can still switch via Change if they want.
    if (directMatch && directMatch.is_hiring && !currentSelectedHubId) {
        currentSelectedHubId = directMatch.id;
    }

    if (directMatch) {
        if (directMatch.is_hiring) {
            if (noticeEl) noticeEl.style.display = 'none';
        } else {
            if (noticeEl) {
                noticeEl.style.display = 'block';
                noticeEl.innerHTML = `
                <div class="hub-notice warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div>
                        <strong>The ${directMatch.municipality} Hub is currently not hiring.</strong>
                        <div style="margin-top:2px;color:#c2410c">The admin has closed applications here. Please select an available nearby hub in <strong>${province}</strong> below.</div>
                    </div>
                </div>`;
            }
        }
    } else {
        if (noticeEl) {
            noticeEl.style.display = 'block';
            noticeEl.innerHTML = `
            <div class="hub-notice rider-hub-error" role="alert">
                <div>
                    <strong>${selectedCompany} has no hub in ${municipality}.</strong>
                    <div style="margin-top:2px">Please select one of their available hubs in <strong>${province}</strong> below.</div>
                </div>
            </div>`;
        }
    }

    updateSelectedHubPreview();
}

function updateSelectedHubPreview() {
    const container = document.getElementById('hubSelectionContainer');
    const province     = (document.getElementById('province')?.value || '').trim();
    const municipality = (document.getElementById('municipality')?.value || '').trim();

    document.getElementById('selected_hub_id').value = currentSelectedHubId || '';
    document.getElementById('changeSelectedHub').hidden=!currentSelectedHubId;
    if (typeof updateVehicleTypeAvailability === 'function') updateVehicleTypeAvailability();

    if (!currentSelectedHubId) {
        container.innerHTML = `
        <div class="no-hub-selected-card">
            <div style="display:flex;align-items:center;gap:12px">
                <div class="no-hub-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div>
                    <strong style="font-size:13px;color:#1e293b">No hub selected yet</strong>
                    <p style="margin:2px 0 0;font-size:11.5px;color:var(--auth-muted)">Please choose an available hub in ${province}.</p>
                </div>
            </div>
            <button type="button" class="btn-browse-hubs" onclick="openHubModal()">
                <span>Select Hub</span>
            </button>
        </div>`;
        return;
    }

    const hub = activeProvHubs.find(h => h.id === currentSelectedHubId);
    if (!hub) return;

    const isDirectMatch = hub.municipality.toLowerCase() === municipality.toLowerCase();

    container.innerHTML = `
    <div class="selected-hub-card ${isDirectMatch ? '' : 'nearby-chosen'}">
        <div class="selected-hub-main">
            <div class="selected-hub-details">
                <div class="selected-hub-header">
                    <span class="selected-hub-name">${hub.municipality} Hub</span>
                    <span class="hub-badge ${hub.is_hiring ? 'hub-badge-hiring' : 'hub-badge-not-hiring'}">
                        ${hub.is_hiring ? (isDirectMatch ? 'Actively Hiring' : 'Hiring') : (hub.hiring_status || 'Not Hiring')}
                    </span>
                    ${hub.is_regional_hub ? '<span class="hub-badge hub-badge-regional">Regional</span>' : ''}
                </div>
            </div>
        </div>
    </div>`;
}

// ── Hubs Modal Implementation ──
function openHubModal() {
    tempModalHubId = activeProvHubs.some(h => h.id === currentSelectedHubId && h.is_hiring) ? currentSelectedHubId : null;

    const province = (document.getElementById('province')?.value || '').trim();
    const selectedCompany = document.querySelector('input[name="business_name"]:checked')?.value || '';

    const titleEl = document.getElementById('hubsModalTitle');
    const subEl   = document.getElementById('hubsModalSub');
    if (titleEl) titleEl.textContent = 'Available hubs';
    if (subEl) subEl.textContent = `${selectedCompany} · ${province}`;

    const searchInput = document.getElementById('hubsSearchInput');
    if (searchInput) searchInput.value = '';

    renderModalHubList('');

    const modal = document.getElementById('hubsModal');
    if (modal && !modal.open) modal.showModal();
}

function closeHubModal() {
    const modal = document.getElementById('hubsModal');
    if (modal?.open) modal.close();
}

function renderModalHubList(searchTerm) {
    const container = document.getElementById('hubsModalList');
    if (!container) return;

    const municipality = (document.getElementById('municipality')?.value || '').trim();
    const term = searchTerm.toLowerCase();

    const filtered = activeProvHubs.filter(h =>
        h.municipality.toLowerCase().includes(term) ||
        h.province.toLowerCase().includes(term)
    ).sort((a,b) => Number(b.is_hiring)-Number(a.is_hiring) || Number(b.municipality.toLowerCase()===municipality.toLowerCase())-Number(a.municipality.toLowerCase()===municipality.toLowerCase()) || a.municipality.localeCompare(b.municipality));
    container.replaceChildren();

    if (filtered.length === 0) {
        const empty=document.createElement('p');empty.className='hubs-empty-state';empty.setAttribute('role','status');
        empty.textContent=searchTerm ? 'No hubs match your search.' : 'No hubs are available in this province.';
        container.append(empty);updateModalFooterSelection();
        return;
    }

    filtered.forEach(h => {
        const isDirectMatch = h.municipality.toLowerCase() === municipality.toLowerCase();
        const isSelected = h.id === tempModalHubId;

        const card=document.createElement('label');card.id='modalHubCard_'+h.id;
        card.className='hub-choice-card'+(isSelected?' selected':'')+(!h.is_hiring?' not-hiring':'');
        const radio=document.createElement('input');radio.type='radio';radio.name='modal_hub_choice';radio.id='modalHubChoice_'+h.id;radio.value=h.id;
        radio.disabled=!h.is_hiring;radio.checked=isSelected&&!!h.is_hiring;
        radio.addEventListener('change',()=>selectModalHub(h.id));
        const copy=document.createElement('span');copy.className='hub-choice-copy';
        const name=document.createElement('strong');name.textContent=h.municipality+' Hub';
        const location=document.createElement('span');location.className='hub-choice-location';location.textContent=[isDirectMatch?'Your municipality':'',h.is_regional_hub?'Regional hub':''].filter(Boolean).join(' · ');
        copy.append(name);if(location.textContent)copy.append(location);
        const status=document.createElement('span');status.className='hub-badge '+(h.is_hiring?'hub-badge-hiring':'hub-badge-not-hiring');status.textContent=h.is_hiring?'Hiring':(h.hiring_status||'Not hiring');
        card.append(radio,copy,status);container.append(card);
    });

    updateModalFooterSelection();
}

function selectModalHub(hubId) {
    if(!activeProvHubs.some(h=>h.id===hubId&&h.is_hiring))return;
    tempModalHubId = hubId;
    document.querySelectorAll('#hubsModalList .hub-choice-card').forEach(c => c.classList.remove('selected'));
    const card = document.getElementById('modalHubCard_' + hubId);
    if (card) card.classList.add('selected');
    const radio = document.getElementById('modalHubChoice_' + hubId);
    if (radio) radio.checked = true;
    updateModalFooterSelection();
}

function updateModalFooterSelection() {
    const textEl = document.getElementById('hubsModalSelectedText');
    const confirmBtn = document.getElementById('btnConfirmHubSelection');
    if (!textEl || !confirmBtn) return;
    const chosen = activeProvHubs.find(h => h.id === tempModalHubId);
    if (chosen && chosen.is_hiring) {
        textEl.textContent = `${chosen.municipality} Hub`;
        confirmBtn.disabled = false;
    } else {
        textEl.textContent = 'None';
        confirmBtn.disabled = true;
    }
}

function confirmHubModalSelection() {
    if (!activeProvHubs.some(h=>h.id===tempModalHubId&&h.is_hiring)) return;
    currentSelectedHubId = tempModalHubId;
    updateSelectedHubPreview();
    document.getElementById('hubSelectError').style.display = 'none';
    closeHubModal();
}

function filterHubsModal(value) {
    renderModalHubList(value.trim());
}

