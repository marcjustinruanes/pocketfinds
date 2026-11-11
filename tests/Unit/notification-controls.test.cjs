const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function fixture(ok = true, native = false) {
    const listeners = {}, panelEvents = {}, attributes = {}, state = new Set(['open']);
    const removed = [];
    const dot = {remove: () => removed.push('dot')};
    const count = {remove: () => removed.push('count')};
    const badge = {remove: () => removed.push('badge')};
    const toggle = {dataset:{dropdownToggle:'notifications'}, setAttribute:(key,value)=>attributes[key]=value, getBoundingClientRect:()=>({bottom:40}), focus(){this.focused=true;}};
    const panel = {id:'notifications', classList:{contains:key=>state.has(key), remove:key=>state.delete(key)}, addEventListener:(name,callback)=>panelEvents[name]=callback};
    const menuEvents={}, styles={};
    const menu={open:false, style:{setProperty:(key,value)=>styles[key]=value}, querySelector:()=>toggle, contains:target=>target===toggle||target===form, addEventListener:(name,callback)=>menuEvents[name]=callback};
    const otherMenu={open:true};
    const item = {classList:{add:key=>state.add(key)}, querySelectorAll:()=>[dot]};
    const button = {disabled:false};
    class Form {
        constructor(){this.action='https://example.test/account/notifications/read-all';this.dataset={};this.hidden=false;this.errors=[];}
        querySelector(selector){return selector.startsWith('button') ? button : null;}
        append(node){this.errors.push(node);}
    }
    const form = new Form();
    const document = {
        querySelector:()=>null,
        querySelectorAll(selector){
            if(selector==='.pf-notification-menu') return native ? [menu] : [];
            if(selector==='.pf-buyer-menu[open]') return [menu,otherMenu];
            if(selector==='.dropdown-panel.pf-buyer-notifications') return [panel];
            if(selector==='[data-dropdown-toggle]') return [toggle];
            if(selector==='.notif-item') return [item];
            if(selector==='.notification-entry') return [];
            if(selector.includes('.notif-dropdown-head')) return [count];
            if(selector.includes('[aria-label="Notifications"]')) return [badge];
            if(selector==='form') return [form];
            return [];
        },
        addEventListener:(name,callback)=>listeners[name]=callback,
        createElement:()=>({dataset:{},setAttribute(){}}),
    };
    let observer;
    vm.runInNewContext(fs.readFileSync('public/js/navigation.js','utf8'), {
        document, window:{location:{href:'https://example.test/orders'},addEventListener(){}}, URL,
        HTMLFormElement:Form, FormData:class {},
        MutationObserver:class {constructor(callback){observer=callback;}observe(){}},
        fetch:async()=>({ok}),
    });
    listeners.DOMContentLoaded();
    return {listeners,panelEvents,attributes,state,removed,toggle,form,button,observer,menu,menuEvents,otherMenu,styles};
}

test('notification controls keep the panel open and support Escape and expanded state', () => {
    const f=fixture();
    assert.equal(f.attributes['aria-controls'],'notifications');
    assert.equal(f.attributes['aria-expanded'],'true');
    let stopped=false;
    f.panelEvents.click({stopPropagation(){stopped=true;}});
    assert.ok(stopped);
    assert.ok(f.state.has('open'));
    f.panelEvents.keydown({key:'Escape',preventDefault(){}});
    f.observer();
    assert.equal(f.attributes['aria-expanded'],'false');
    assert.ok(f.toggle.focused);
});

test('mark all as read updates dots and count without closing the notification panel', async () => {
    const f=fixture();
    let prevented=false;
    await f.listeners.submit({target:f.form,preventDefault(){prevented=true;}});
    assert.ok(prevented);
    assert.ok(f.state.has('open'));
    assert.ok(f.state.has('read'));
    assert.deepEqual(f.removed,['dot','count','badge']);
    assert.ok(f.form.hidden);
    assert.equal(f.button.disabled,false);
});

test('failed mark-as-read requests leave unread controls available for retry', async () => {
    const f=fixture(false);
    await f.listeners.submit({target:f.form,preventDefault(){}});
    assert.ok(f.state.has('open'));
    assert.equal(f.state.has('read'),false);
    assert.deepEqual(f.removed,[]);
    assert.equal(f.form.hidden,false);
    assert.equal(f.form.errors.length,1);
    assert.equal(f.button.disabled,false);
});

test('the shared native menu closes other menus, positions consistently, and closes outside', () => {
    const f=fixture(true,true);
    f.menu.open=true;
    f.menuEvents.toggle();
    assert.equal(f.attributes['aria-expanded'],'true');
    assert.equal(f.otherMenu.open,false);
    assert.equal(f.styles['--notification-panel-top'],'52px');
    f.listeners.click({target:f.toggle});
    assert.ok(f.menu.open);
    f.listeners.click({target:{}});
    assert.equal(f.menu.open,false);
});

test('shared native menus keep open while marking read and restore focus on Escape', async () => {
    const f=fixture(true,true);
    f.menu.open=true;
    await f.listeners.submit({target:f.form,preventDefault(){}});
    assert.ok(f.menu.open);
    f.listeners.keydown({key:'Escape'});
    assert.equal(f.menu.open,false);
    assert.ok(f.toggle.focused);
});
