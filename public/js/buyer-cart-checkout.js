(() => {
    const form = document.getElementById('checkoutForm');
    if (!form?.dataset.quoteUrl) return;
    const feedback = document.getElementById('cartCheckoutFeedback');
    let version = 0, timer;
    function shippingVoucherStatus(quote,error,loading=false) {
        const status=document.getElementById('shippingVoucherStatus');if(!status)return;
        const select=document.querySelector('select[name="vouchers[shipping]"]');
        const code=select?.value;
        if(!code){status.textContent='No shipping voucher selected. Choose one from the Shipping voucher dropdown; claiming it does not apply it automatically.';return;}
        if(error){status.textContent='Shipping voucher not applied: '+error;return;}
        if(loading){status.textContent='Checking shipping voucher '+code+'…';return;}
        const rows=Object.values(quote.orders);
        const saved=rows.flatMap(row=>row.vouchers||[]).filter(v=>v.kind==='shipping'&&v.code===code).reduce((sum,v)=>sum+Number(v.amount),0);
        const shipping=rows.reduce((sum,row)=>sum+Number(row.shipping_amount),0);
        const money=value=>'₱'+Number(value).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
        if(saved>0)status.textContent=`Applied ${code}: saves ${money(saved)} on shipping. Shipping ${money(shipping)} → ${money(Math.max(0,shipping-saved))}.`;
        else if(rows.some(row=>row.shipping_pending))status.textContent='Shipping voucher pending: '+(quote.warnings||['Complete the delivery address and seller shipping setup.']).join(' ');
        else status.textContent=shipping===0?'Shipping is already free, so this voucher has no fee to deduct.':'This shipping voucher did not reduce the fee. Check its shop and eligibility requirements.';
    }
    function checkoutError(message) {
        feedback.textContent=message;
        feedback.setAttribute?.('role','alert');
        feedback.classList?.add('auth-error');
        feedback.tabIndex=-1;
        feedback.scrollIntoView?.({behavior:'smooth',block:'center'});
        feedback.focus?.({preventScroll:true});
    }
    function filterVouchers() {
        const sellers=new Set([...document.querySelectorAll('.cart-shop')].filter(shop=>shop.querySelector('.cart-select:checked')).map(shop=>shop.dataset.sellerId));
        document.querySelectorAll('[data-checkout-voucher]').forEach(select=>{
            const seller=select.dataset?.voucherSeller;
            const available=sellers.size>0&&(!seller||sellers.has(seller));
            const group=select.closest?.('[data-voucher-group]');if(group)group.hidden=!available;
            select.disabled=!available;
            for(const option of select.options||[]) {
                const owner=option.dataset?.voucherSeller;
                option.hidden=!!owner&&!sellers.has(owner);option.disabled=option.hidden;
                if(option.hidden&&option.selected)select.value='';
            }
            if(!available)select.value='';
        });
    }
    const details = () => ({
        items: [...document.querySelectorAll('.cart-select:checked')].map(input => input.closest('.cart-item').querySelector('[data-edit-key]')?.dataset.editKey).filter(Boolean),
        delivery_address_id: document.querySelector('[name="delivery_address_radio"]:checked')?.value || '',
        payment_method: document.getElementById('paymentNameSelect')?.value || '',
        checkout_id: form.elements.checkout_id.value,
        vouchers: [...document.querySelectorAll('[data-checkout-voucher]')].filter(select=>!select.disabled).map(select => select.value).filter(Boolean),
    });
    async function calculate(data, showErrors) {
        const current = ++version;
        form.elements.quote_hash.value = '';
        if (!data.items.length || (showErrors && (!data.delivery_address_id || !data.payment_method))) {
            feedback.textContent = data.items.length?'Select a delivery address and a payment method to place your order.':'Select items to calculate your total.';
            if(showErrors)checkoutError(feedback.textContent);
            if(!data.items.length){document.getElementById('cartDiscountRow').style.display='none';document.getElementById('cartSubtotal').textContent='₱0.00';document.getElementById('cartTotal').textContent='₱0.00';document.getElementById('cartShipping').textContent='—';const dock=document.getElementById('cartDockTotal');if(dock)dock.textContent='₱0.00';}
            return false;
        }
        data.preview = !showErrors;
        shippingVoucherStatus(null,null,true);
        feedback.classList?.remove('auth-error');
        feedback.setAttribute?.('role','status');
        feedback.textContent = 'Calculating shipping and discounts…';
        try {
            const response = await fetch(form.dataset.quoteUrl, {method:'POST', headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(data)});
            const result = await response.json();
            if (current !== version) return false;
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to calculate checkout.');
            const rows = Object.values(result.quote.orders);
            const money = value => '₱' + Number(value).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
            const itemLines = rows.flatMap(row=>row.items);
            document.querySelectorAll('.cart-item').forEach(element=>{
                const key = element.querySelector('[data-edit-key]')?.dataset.editKey;
                const lines = itemLines.filter(item=>String(item.cart_id)===key);
                if (!lines.length) return;
                const lineTotal = lines.reduce((sum,item)=>sum+Number(item.price)*Number(item.qty),0);
                const quantity = lines.reduce((sum,item)=>sum+Number(item.qty),0);
                element.querySelector('.cart-item-total').textContent = money(lineTotal);
                element.querySelector('.cart-item-quantity').textContent = lines.map(item=>`${item.qty} × ${money(item.price)}${item.flash_sale_id?' (flash sale)':''}`).join(' + ');
                element.querySelector('.cart-select').dataset.price = lineTotal / quantity;
            });
            document.getElementById('cartSubtotal').textContent = money(rows.reduce((sum,row)=>sum+Number(row.subtotal),0));
            const shippingPending = rows.some(row=>row.shipping_pending);
            document.getElementById('cartShipping').textContent = shippingPending?'Pending':money(rows.reduce((sum,row)=>sum+Number(row.shipping_amount),0));
            const totalLabel=document.getElementById('cartTotalLabel');if(totalLabel)totalLabel.textContent=shippingPending?'Estimated total':'Total';
            const dockLabel=document.getElementById('cartDockTotalLabel');if(dockLabel)dockLabel.textContent=shippingPending?'Estimated total':'Total';
            const discount = rows.reduce((sum,row)=>sum+Number(row.discount_amount),0);
            document.getElementById('cartDiscountRow').style.display = discount ? '' : 'none';
            document.getElementById('cartDiscountLabel').textContent = 'Voucher discounts';
            document.getElementById('cartDiscount').textContent = '− ' + money(discount);
            document.getElementById('cartTotal').textContent = money(result.quote.total);
            const dock = document.getElementById('cartDockTotal'); if (dock) dock.textContent = money(result.quote.total);
            form.elements.quote_hash.value = showErrors?(result.hash||''):'';
            shippingVoucherStatus(result.quote);
            feedback.textContent = result.quote.warnings?.length?result.quote.warnings.join(' '):rows.map(row=>`${row.items[0].seller_slug}: ${row.chargeable_kg} kg shipping ${money(row.shipping_amount)}`).join(' · ');
            return true;
        } catch(error) { if (current === version) {shippingVoucherStatus(null,error.message);if(showErrors)checkoutError(error.message);else feedback.textContent=error.message;document.getElementById('cartDiscountRow').style.display='none';document.getElementById('cartTotal').textContent='—';const dock=document.getElementById('cartDockTotal');if(dock)dock.textContent='—';} return false; }
    }
    window.prepareBuyerCheckout = data => { clearTimeout(timer); filterVouchers(); return calculate({...details(),...data},true); };
    const refresh = () => {
        filterVouchers();
        ++version; form.elements.quote_hash.value = ''; clearTimeout(timer);
        timer = setTimeout(()=>calculate(details(),false),200);
    };
    document.addEventListener('change',event=>{
        if (event.target.matches('.cart-select,.cart-shop-select,#cartSelectAll,[name="delivery_address_radio"],#paymentNameSelect,#paymentTypeSelect,[name="cart_payment_choice"],[data-checkout-voucher]')) refresh();
    });
    document.addEventListener('buyer-cart-summary-changed',refresh);
    refresh();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',refresh);
})();
