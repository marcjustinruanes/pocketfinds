const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

function cart(fetch) {
    const events={};let scheduled;
    const address={value:'7'};
    const shops=[{dataset:{sellerId:'1'},selected:true,querySelector(){return this.selected?{}:null;}}];
    const vouchers=[{value:'STORE10',dataset:{voucherSeller:'1'},options:[]},{value:'PLAT30',dataset:{},options:[]}];
    const nodes = Object.fromEntries(['cartCheckoutFeedback','cartSubtotal','cartShipping','cartDiscountRow','cartDiscountLabel','cartDiscount','cartTotal','cartDockTotal','paymentNameSelect'].map(id=>[id,{textContent:'',style:{},value:'2'}]));
    const form = {dataset:{quoteUrl:'/quote'},elements:{checkout_id:{value:'checkout-id'},quote_hash:{value:''}}};
    nodes.checkoutForm = form;
    const document = {
        readyState:'complete',getElementById:id=>nodes[id],addEventListener(name,callback){events[name]=callback;},
        querySelectorAll(selector) {
            if (selector === '.cart-shop') return shops;
            if (selector === '.cart-select:checked') return [{closest:()=>({querySelector:()=>({dataset:{editKey:'actual-cart-row-id'}})})}];
            if (selector === '[data-checkout-voucher]') return vouchers;
            return [];
        },
        querySelector(selector) { return selector.includes('csrf')?{content:'csrf-token'}:address; }
    };
    const context={document,window:{},fetch,setTimeout:callback=>{scheduled=callback;return 1;},clearTimeout(){}};
    vm.runInNewContext(fs.readFileSync('public/js/buyer-cart-checkout.js','utf8'),context);
    return {nodes,form,address,shops,vouchers,events,runTimer:()=>scheduled(),prepare:context.window.prepareBuyerCheckout};
}

const quote = total => ({ok:true,json:async()=>({hash:'server-reviewed-hash',quote:{total,orders:{shop:{subtotal:300,shipping_amount:80,discount_amount:40,chargeable_kg:2,items:[{seller_slug:'shop'}]}}}})});

test('native cart sends real cart IDs and selected vouchers, then displays the server quote',async()=>{
    let body;
    const ui=cart(async(url,options)=>{body=JSON.parse(options.body);return quote(340);});
    assert.equal(await ui.prepare({buyer_note:'Deliver to reception'}),true);
    assert.deepEqual(body.items,['actual-cart-row-id']);
    assert.deepEqual(body.vouchers,['STORE10','PLAT30']);
    assert.equal(body.delivery_address_id,'7');
    assert.equal(ui.form.elements.quote_hash.value,'server-reviewed-hash');
    assert.match(ui.nodes.cartTotal.textContent,/340\.00/);
    assert.match(ui.nodes.cartShipping.textContent,/80\.00/);
    assert.match(ui.nodes.cartCheckoutFeedback.textContent,/2 kg/);
});

test('unserviceable checkout cannot retain an earlier successful quote',async()=>{
    let fail=false;
    const ui=cart(async()=>fail?{ok:false,json:async()=>({errors:{delivery_address_id:['Shop does not deliver to this region.']}})}:quote(340));
    assert.equal(await ui.prepare({}),true);
    fail=true;
    assert.equal(await ui.prepare({}),false);
    assert.equal(ui.form.elements.quote_hash.value,'');
    assert.match(ui.nodes.cartCheckoutFeedback.textContent,/does not deliver/);
    assert.equal(ui.nodes.cartTotal.textContent,'—');
});

test('changing a voucher reduces the preview total before address and payment are complete',async()=>{
    let body;
    const ui=cart(async(url,options)=>{
        body=JSON.parse(options.body);
        return {ok:true,json:async()=>({hash:null,quote:{total:260,warnings:['Shipping is pending.'],orders:{shop:{subtotal:300,shipping_amount:0,shipping_pending:true,discount_amount:40,items:[{seller_slug:'shop'}]}}}})};
    });
    ui.nodes.paymentNameSelect.value='';ui.address.value='';
    ui.events.change({target:{matches:()=>true}});
    assert.equal(await ui.runTimer(),true);
    assert.equal(body.preview,true);assert.deepEqual(body.vouchers,['STORE10','PLAT30']);
    assert.match(ui.nodes.cartTotal.textContent,/260\.00/);assert.match(ui.nodes.cartDiscount.textContent,/40\.00/);
    assert.equal(ui.nodes.cartShipping.textContent,'Pending');assert.equal(ui.form.elements.quote_hash.value,'');
});

test('deselecting a shop hides and clears its store and shipping vouchers',async()=>{
    let body;
    const ui=cart(async(url,options)=>{body=JSON.parse(options.body);return quote(340);});
    const group={hidden:false};ui.vouchers[0].closest=()=>group;
    const shipping={value:'SHIP1',dataset:{},options:[{value:'SHIP1',dataset:{voucherSeller:'1'},selected:true}]};
    ui.vouchers.push(shipping);
    ui.shops[0].selected=false;
    ui.shops.push({dataset:{sellerId:'2'},querySelector:()=>({})});
    await ui.prepare({});
    assert.equal(group.hidden,true);assert.equal(ui.vouchers[0].disabled,true);assert.equal(ui.vouchers[0].value,'');
    assert.equal(shipping.options[0].hidden,true);assert.equal(shipping.value,'');
    assert.deepEqual(body.vouchers,['PLAT30']);
});

test('an older quote response cannot replace the latest cart calculation',async()=>{
    const pending=[];
    const ui=cart(()=>new Promise(resolve=>pending.push(resolve)));
    const first=ui.prepare({}),second=ui.prepare({});
    pending[1](quote(250)); assert.equal(await second,true);
    pending[0](quote(999)); assert.equal(await first,false);
    assert.match(ui.nodes.cartTotal.textContent,/250\.00/);
});
