const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function widgetFixture(){
 const events={},windowEvents={},classes=new Set(),attributes={},body={children:[],replaceChildren(...children){this.children=children;},querySelector(){return this.children[0];}};
 const closeButton={focus(){this.focused=true;}};
 const assistant={hidden:false},assistantButton={setAttribute:(name,value)=>attributes['assistant-'+name]=value};
 const widget={dataset:{},classList:{add:name=>classes.add(name),remove:name=>classes.delete(name),contains:name=>classes.has(name)},setAttribute:(name,value)=>attributes[name]=value,querySelector:selector=>selector==='.chat-widget-body'?body:closeButton};
 const opener={dataset:{sellerMessages:'https://example.test/seller/messages?buyer=2'},focus(){this.focused=true;}};
 const document={getElementById:id=>id==='sellerMessagesWidget'?widget:assistant,querySelector:()=>assistantButton,createElement:()=>({contentWindow:{}}),addEventListener:(name,callback)=>events[name]=callback};
 vm.runInNewContext(fs.readFileSync('public/js/seller-messages-widget.js','utf8'),{document,window:{addEventListener:(name,callback)=>windowEvents[name]=callback},location:{origin:'https://example.test',pathname:'/seller/orders'},URL});
 const click=(extra={})=>{let prevented=false;events.click({target:{closest:selector=>selector==='[data-seller-messages-close]'?null:opener},button:0,preventDefault(){prevented=true;},...extra});return prevented;};
 return {events,windowEvents,classes,attributes,body,assistant,closeButton,opener,click};
}

test('seller chat opens the requested buyer thread, hides the assistant, and closes cleanly',()=>{
 const f=widgetFixture();assert.ok(f.click());assert.ok(f.classes.has('open'));assert.equal(f.attributes['aria-hidden'],'false');
 const url=new URL(f.body.children[0].src);assert.equal(url.searchParams.get('buyer'),'2');assert.equal(url.searchParams.get('embedded'),'1');
 assert.ok(f.assistant.hidden);assert.equal(f.attributes['assistant-aria-expanded'],'false');assert.ok(f.closeButton.focused);
 f.events.keydown({key:'Escape'});assert.equal(f.classes.has('open'),false);assert.equal(f.body.children.length,0);assert.equal(f.attributes['aria-hidden'],'true');assert.ok(f.opener.focused);
});

test('seller chat ignores modified clicks and untrusted close messages',()=>{
 const f=widgetFixture();assert.equal(f.click({ctrlKey:true}),false);assert.equal(f.body.children.length,0);
 f.click();const source=f.body.children[0].contentWindow;
 f.windowEvents.message({origin:'https://foreign.test',source,data:'seller-close-messages'});assert.ok(f.classes.has('open'));
 f.windowEvents.message({origin:'https://example.test',source:{},data:'seller-close-messages'});assert.ok(f.classes.has('open'));
 f.windowEvents.message({origin:'https://example.test',source,data:'seller-close-messages'});assert.equal(f.classes.has('open'),false);
});

test('seller conversation search and send scripts remain valid JavaScript',()=>{
 const scripts=[...fs.readFileSync('resources/views/seller/messages.blade.php','utf8').matchAll(/<script>([\s\S]*?)<\/script>/g)];
 assert.ok(scripts.length>=2);
 for(const [,script] of scripts)new vm.Script(script.replace(/\{\{[\s\S]*?\}\}/g,'0').replace(/@json\([^)]*\)/g,'null'));
});
