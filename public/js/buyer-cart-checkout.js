(() => {
    const form = document.getElementById('checkoutForm');
    if (!form?.dataset.quoteUrl) return;
    const feedback = document.getElementById('cartCheckoutFeedback');
    let version = 0, timer;
    function shippingVoucherStatus(quote,error,loading=false) {
        const status=document.getElementById('shippingVoucherStatus');if(!status)return;
        const select=document.querySelector('select[name="vouchers[shipping]"]');
        const code=select?.value;
        const guaranteedRows=quote ? Object.values(quote.orders).filter(row=>row.delivery_policy && row.free_shipping_eligible !== false) : [];
        if(guaranteedRows.length) {
            status.textContent=guaranteedRows.some(row=>row.shipping_pending)
                ? 'Free shipping is included. Complete your address and shipping details to confirm availability.'
                : 'Free shipping applied to eligible seller delivery areas.';
            return;
        }
        if(!code){status.textContent='';return;}
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
    function checkoutFeedback(message,error=false) {
        feedback.textContent=message;feedback.hidden=!message;
        feedback.setAttribute?.('role',error?'alert':'status');
        feedback.classList?.remove('auth-error','is-error');
        if(error)feedback.classList?.add('is-error');
    }
    function readableMessage(message) {
        if(message.includes('seller must configure a rate'))return 'Delivery is unavailable for this address. Choose another address.';
        if(message.includes('seller must add product weights'))return 'Some items cannot be shipped yet. Contact the seller.';
        return message;
    }
    function checkoutError(message,focus=true) {
        checkoutFeedback(readableMessage(message),true);
        if(!focus)return;
        feedback.tabIndex=-1;
        feedback.scrollIntoView?.({behavior:'smooth',block:'center'});
        feedback.focus?.({preventScroll:true});
    }
    function filterVouchers(serverOptions=null) {
        const shops=[...document.querySelectorAll('.cart-shop')].filter(shop=>shop.querySelector('.cart-select:checked'));
        const sellers=new Set(shops.map(shop=>shop.dataset.sellerId));
        const totals=new Map(shops.map(shop=>[String(shop.dataset.sellerId),[...(shop.querySelectorAll?.('.cart-select:checked')||[])].reduce((sum,input)=>sum+Number(input.dataset.price)*Number(input.dataset.qty),0)]));
        const authoritative=Array.isArray(serverOptions)?new Map(serverOptions.map(offer=>[offer.code,offer])):null;
        const reasons=new Map();
        document.querySelectorAll('[data-checkout-voucher]').forEach(select=>{
            const seller=select.dataset?.voucherSeller;
            const available=sellers.size>0&&(!seller||sellers.has(seller));
            const group=select.closest?.('[data-voucher-group]');
            select.disabled=!available;
            for(const option of select.options||[]) {
                const owner=option.dataset?.voucherSeller;
                let reason=(!available||(owner&&!sellers.has(owner)))?'Select items from this shop.':'';
                const matching=owner?shops.filter(shop=>String(shop.dataset.sellerId)===String(owner)):shops;
                let products=[];try{products=JSON.parse(option.dataset?.products||'[]');}catch(_){}
                const eligibleShops=products.length?matching.filter(shop=>[...(shop.querySelectorAll?.('.cart-select:checked')||[])].some(input=>products.includes(input.dataset.productId))):matching;
                if(products.length&&!eligibleShops.length)reason='Select eligible products for this voucher.';
                const subtotal=eligibleShops.reduce((sum,shop)=>sum+(totals.get(String(shop.dataset.sellerId))||0),0);
                const remaining=Math.max(0,Number(option.dataset?.minimumSpend||0)-subtotal);
                if(!reason&&remaining>0)reason=`Spend ₱${remaining.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2})} more to use this voucher.`;
                const server=authoritative?.get(option.value);
                if(server)reason=server.eligible?'':server.reason;
                option.hidden=!!option.value&&!!reason;option.disabled=option.hidden;
                if(option.value)reasons.set(option.value,{eligible:!reason,reason});
                if(option.hidden&&(option.selected||select.value===option.value))select.value='';
            }
            const hasOffers=[...(select.options||[])].some(option=>option.value&&!option.hidden);
            if(group)group.hidden=!available||!hasOffers;
            if((select.options||[]).length&&!hasOffers)select.disabled=true;
            if(!available)select.value='';
        });
        document.querySelectorAll('[data-voucher-offer-code]').forEach(card=>{
            const state=reasons.get(card.dataset.voucherOfferCode)||{eligible:false,reason:'Select items to check this offer.'};
            const reason=card.querySelector('[data-voucher-offer-reason]'),button=card.querySelector('[data-checkout-voucher-apply]');
            if(reason){reason.textContent=state.eligible?'Available for your selected items':state.reason;reason.classList?.toggle('is-eligible',state.eligible);}
            if(button)button.disabled=!state.eligible;
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
            checkoutFeedback(data.items.length?'Select a delivery address and a payment method to place your order.':'Select items to calculate your total.');
            if(showErrors)checkoutError(feedback.textContent);
            if(!data.items.length){document.getElementById('cartDiscountRow').style.display='none';document.getElementById('cartSubtotal').textContent='₱0.00';document.getElementById('cartTotal').textContent='₱0.00';document.getElementById('cartShipping').textContent='—';const dock=document.getElementById('cartDockTotal');if(dock)dock.textContent='₱0.00';}
            return false;
        }
        data.preview = !showErrors;
        shippingVoucherStatus(null,null,true);
        checkoutFeedback('Calculating shipping and discounts…');
        try {
            const response = await fetch(form.dataset.quoteUrl, {method:'POST', headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(data)});
            const result = await response.json();
            if (current !== version) return false;
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to calculate checkout.');
            const rows = Object.values(result.quote.orders);
            filterVouchers(result.quote.voucher_options);
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
            if(result.quote.warnings?.length)checkoutError([...new Set(result.quote.warnings.map(readableMessage))].join(' '),false);
            else checkoutFeedback(rows.map(row=>row.delivery_policy ? `${row.items[0].seller_slug}: ${row.free_shipping_eligible ? 'Free shipping · ' : ''}Delivery ${row.delivery_policy.label}` : `${row.items[0].seller_slug}: ${row.chargeable_kg} kg shipping ${money(row.shipping_amount)}`).join(' · '));
            return true;
        } catch(error) { if (current === version) {shippingVoucherStatus(null,error.message);checkoutError(error.message,showErrors);document.getElementById('cartDiscountRow').style.display='none';document.getElementById('cartTotal').textContent='—';const dock=document.getElementById('cartDockTotal');if(dock)dock.textContent='—';} return false; }
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
    document.addEventListener('click',event=>{
        const button=event.target.closest('[data-checkout-voucher-apply]');if(!button||button.disabled)return;
        const select=[...document.querySelectorAll('[data-checkout-voucher]')].find(field=>[...field.options].some(option=>option.value===button.dataset.checkoutVoucherApply&&!option.disabled));
        if(!select)return;select.value=button.dataset.checkoutVoucherApply;select.dispatchEvent(new Event('change',{bubbles:true}));
        const modal=document.getElementById('checkoutVouchersModal');modal?.classList.remove('open');modal?.setAttribute('aria-hidden','true');document.querySelector('[data-modal-open="checkoutVouchersModal"]')?.focus();
    });
    refresh();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',refresh);
})();
