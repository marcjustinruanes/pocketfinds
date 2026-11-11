const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function fixture(){
 const control=value=>({value,events:{},addEventListener(name,callback){this.events[name]=callback;},focus(){this.focused=true;}});
 const search=control(''),stock=control('all'),category=control('all'),statusFilter=control('all'),counter={},empty={},clear=control('');
 const makeRow=(name,status,quantity,state,price,updated)=>({dataset:{productName:name,search:name.toLowerCase(),status,stock:String(quantity),stockState:state,price:String(price),updated:String(updated)},hidden:false});
 const rows=[makeRow('Fountain','active',3,'low',999,10),makeRow('Wash','pending',8,'available',124,40),makeRow('Soap','active',0,'out',279,30),makeRow('Bottle','archived',7,'setup',100,20)];
 const tabs=['all','active','pending','archived'].map(productStatus=>({...control(''),dataset:{productStatus},classList:{toggle(){}},setAttribute(){}}));
 const order=[];
 const table={tBodies:[{append(row){const previous=order.indexOf(row);if(previous!==-1)order.splice(previous,1);order.push(row);}}]};
 const remove={...control(''),dataset:{productRemove:'remove-1',productName:"Seller's bottle"}};
 let removal;
 const selectors={'#productSearch':search,'#stockFilter':stock,'#productStatusFilter':statusFilter,'#shopCategoryFilter':category,'#productTable':table,'[data-product-result-count]':counter,'[data-product-empty]':empty,'.product-list-meta [data-product-reset]':clear};
 const workspace={querySelector:selector=>selectors[selector],querySelectorAll:selector=>({'[data-product-row]':rows,'[data-product-status]':tabs,'[data-product-reset]':[clear],'[data-product-remove]':[remove]}[selector]??[])};
 vm.runInNewContext(fs.readFileSync('public/js/seller-products.js','utf8'),{document:{querySelector:()=>workspace,getElementById:()=>null},window:{confirmRemoveProduct:(...args)=>removal=args}});
 return {search,stock,category,statusFilter,counter,empty,clear,rows,tabs,table,order,remove,get removal(){return removal;}};
}

test('product search and status filters apply together',()=>{
 const f=fixture();f.statusFilter.value='active';f.statusFilter.events.change();f.search.value='fountain';f.search.events.input();
 assert.deepEqual(f.rows.filter(row=>!row.hidden).map(row=>row.dataset.productName),['Fountain']);
 f.search.value='nothing';f.search.events.input();assert.ok(f.empty.hidden===false);assert.equal(f.table.hidden,false);
 f.search.value='';f.search.events.input();assert.deepEqual(f.rows.filter(row=>!row.hidden).map(row=>row.dataset.productName),['Fountain','Soap']);
});

test('Latest-first ordering and clear filters restore the complete, latest-first inventory',()=>{
 const f=fixture();assert.deepEqual(f.order.map(row=>row.dataset.productName),['Wash','Soap','Bottle','Fountain']);
 f.search.value='nothing';f.search.events.input();assert.equal(f.counter.textContent,'Showing 0 of 4 products');
 f.clear.events.click();assert.ok(f.rows.every(row=>!row.hidden));assert.ok(f.empty.hidden);assert.ok(f.clear.hidden);assert.ok(f.search.focused);assert.equal(f.counter.textContent,'4 products');
});

test('shop category filters combine with status and reset restores uncategorized products',()=>{
 const f=fixture();f.rows[0].dataset.shopCategories='2,4';f.rows[1].dataset.shopCategories='4';
 f.category.value='2';f.category.events.change();assert.deepEqual(f.rows.filter(r=>!r.hidden).map(r=>r.dataset.productName),['Fountain']);
 f.statusFilter.value='pending';f.statusFilter.events.change();assert.equal(f.table.hidden,false);assert.equal(f.empty.hidden,false);
 f.clear.events.click();assert.equal(f.category.value,'all');assert.ok(f.rows.every(r=>!r.hidden));
 f.category.value='uncategorized';f.category.events.change();assert.deepEqual(f.rows.filter(r=>!r.hidden).map(r=>r.dataset.productName),['Soap','Bottle']);
});

test('discard confirmation clears edits only after confirmation and allows keeping edits',()=>{
 const button=()=>({events:{},addEventListener(type,fn){this.events[type]=fn},focus(){this.focused=true}});
 const keep=button(),confirm=button(),events={};const classes=new Set();
 const modal={classList:{add:c=>classes.add(c),remove:c=>classes.delete(c)},querySelector:s=>s==='[data-keep-product-editing]'?keep:confirm,addEventListener:(type,fn)=>events[type]=fn};
 const window={};vm.runInNewContext(fs.readFileSync('public/js/seller-products.js','utf8'),{window,document:{querySelector:()=>null,getElementById:()=>modal}});
 let discarded=0;const trigger=button();
 window.confirmDiscardProductEditing(()=>discarded++,trigger);assert.ok(classes.has('open'));assert.ok(keep.focused);assert.equal(discarded,0);
 keep.events.click();assert.equal(discarded,0);assert.ok(trigger.focused);assert.ok(!classes.has('open'));
 window.confirmDiscardProductEditing(()=>discarded++,trigger);confirm.events.click();assert.equal(discarded,1);assert.ok(!classes.has('open'));
 confirm.events.click();assert.equal(discarded,1);
});
