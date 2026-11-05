const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
function setup(){
 const node=()=>({value:'',dataset:{},textContent:'',listeners:{},addEventListener(name,fn){this.listeners[name]=fn;},removeAttribute(name){delete this[name];}});
 const nodes=Object.fromEntries(['registrationPostalField','municipality','postal_code','municipality_psgc_code','registrationPostalCapsule','region','province'].map(id=>[id,node()]));
 nodes.registrationPostalField.dataset.lookupUrl='http://localhost/register/postal-code';nodes.municipality.selectedOptions=[];
 const calls=[];
 vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../../public/js/registration-postal-code.js'),'utf8'),{document:{getElementById:id=>nodes[id]},URL,AbortController,location:{href:'http://localhost/register/buyer'},MutationObserver:class{observe(){}},fetch:(url,options)=>new Promise(resolve=>calls.push({url,options,resolve}))});
 const select=code=>{nodes.municipality.value='Santa Cruz';nodes.municipality.selectedOptions=[{dataset:{code}}];nodes.municipality.listeners.change();};
 const settle=async(index,data)=>{calls[index].resolve({ok:true,json:async()=>data});await new Promise(r=>setImmediate(r));};
 return {nodes,calls,select,settle};
}
test('postal code comes from the API and resets when its parent address changes',async()=>{
 const c=setup();c.select('043426000');assert.equal(c.nodes.postal_code.value,'');assert.match(c.nodes.registrationPostalCapsule.textContent,/Looking up/);
 await c.settle(0,{postal_code:'4004'});assert.equal(c.nodes.postal_code.value,'4004');assert.equal(c.nodes.municipality_psgc_code.value,'043426000');
 assert.equal(c.nodes.postal_code.readOnly,true);
 c.nodes.province.listeners.change();assert.equal(c.nodes.postal_code.value,'');assert.equal(c.nodes.registrationPostalCapsule.textContent,'');
});
test('a late postal response cannot replace the code for the new municipality',async()=>{
 const c=setup();c.select('043426000');c.select('030711300');await c.settle(1,{postal_code:'2206'});await c.settle(0,{postal_code:'4004'});assert.equal(c.nodes.postal_code.value,'2206');
});
test('missing API data permits optional manual entry',async()=>{
 const c=setup();c.select('043426000');await c.settle(0,{postal_code:null});assert.equal(c.nodes.postal_code.value,'');assert.equal(c.nodes.postal_code.readOnly,false);assert.equal(c.nodes.registrationPostalCapsule.textContent,'Enter if known, or leave blank.');
});
