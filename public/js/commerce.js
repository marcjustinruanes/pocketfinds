(() => {
    const renderCountdowns = () => document.querySelectorAll('[data-countdown]').forEach(el => {
        const seconds = Math.max(0, Math.floor((new Date(el.dataset.countdown) - Date.now()) / 1000));
        el.textContent = seconds ? `${Math.floor(seconds / 86400)}d ${Math.floor(seconds / 3600) % 24}h ${Math.floor(seconds / 60) % 60}m ${seconds % 60}s` : 'Now';
    });
    renderCountdowns(); setInterval(renderCountdowns, 1000);
    document.querySelectorAll('[data-commerce-regions]').forEach(async select => {
        select.disabled = true;
        try {
            const response = await fetch('https://psgc.gitlab.io/api/regions/');
            if (!response.ok) throw new Error();
            const regions = await response.json();
            select.replaceChildren(new Option('Select region', ''));
            regions.sort((a,b) => a.name.localeCompare(b.name)).forEach(region => select.add(new Option(region.name, region.name)));
            select.disabled = false;
        } catch { select.replaceChildren(new Option('Unable to load regions. Refresh to retry.', '')); }
    });
    document.querySelectorAll('[data-edit-shipping-rate]').forEach(button=>button.addEventListener('click',()=>{
        const container=button.closest('#shipping-rates'),rateForm=container.querySelector('form'),status=container.querySelector('#shippingRateStatus');
        if(rateForm.elements.region.disabled){status.textContent='Wait for regions to finish loading, then select Edit rate again.';return;}
        rateForm.elements.region.value=button.dataset.region;
        if(!rateForm.elements.region.value){status.textContent='This region could not be matched. Select its current region name from the list.';return;}
        rateForm.elements.first_kg.value=button.dataset.firstKg;rateForm.elements.additional_kg.value=button.dataset.additionalKg;rateForm.elements.is_active.value=button.dataset.active;
        status.textContent='Editing '+button.dataset.region+'. Save region rate to apply your changes.';rateForm.scrollIntoView({behavior:'smooth',block:'center'});rateForm.elements.first_kg.focus({preventScroll:true});
    }));
    const form = document.getElementById('commerce-checkout');
    if (!form) return;
    const summary = form.querySelector('[data-quote-summary]');
    const place = form.querySelector('[data-place-order]');
    let version = 0;
    form.addEventListener('input', () => { ++version; form.elements.quote_hash.value = ''; place.disabled = true; summary.textContent = 'Details changed. Recalculate the total to continue.'; });
    form.querySelector('[data-review-quote]').addEventListener('click', async event => {
        if (!form.reportValidity()) return;
        const requestVersion = ++version;
        event.target.disabled = true; place.disabled = true; summary.textContent = 'Calculating…';
        try {
            const response = await fetch(form.dataset.quoteUrl, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (requestVersion !== version) return;
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Unable to calculate checkout.');
            summary.replaceChildren();
            Object.values(data.quote.orders).forEach(order => {
                const line = document.createElement('p');
                line.textContent = `${order.items[0].seller_slug}: merchandise ₱${Number(order.subtotal).toFixed(2)} + shipping ₱${Number(order.shipping_amount).toFixed(2)} (${order.chargeable_kg} kg) − discounts ₱${Number(order.discount_amount).toFixed(2)} = ₱${Number(order.total).toFixed(2)}`;
                summary.append(line);
                order.items.forEach(item=>{const p=document.createElement('p');p.textContent=`${item.name}${item.flash_sale_id?' (flash price)':''}: ${item.qty} × ₱${Number(item.price).toFixed(2)}`;summary.append(p);});
                order.vouchers.forEach(voucher => { const p = document.createElement('p'); p.textContent = `${voucher.code}: −₱${Number(voucher.amount).toFixed(2)}`; summary.append(p); });
            });
            const total = document.createElement('strong'); total.textContent = `Total: ₱${Number(data.quote.total).toFixed(2)}`; summary.append(total);
            form.elements.quote_hash.value = data.hash; place.disabled = false;
        } catch (error) { if (requestVersion === version) summary.textContent = error.message; }
        finally { event.target.disabled = false; }
    });
    form.addEventListener('submit', event => {
        if (!form.elements.quote_hash.value) { event.preventDefault(); return; }
        place.disabled = true; place.textContent = 'Submitting…';
    });
})();
