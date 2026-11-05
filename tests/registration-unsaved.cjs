const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(native = false) {
    const target = () => ({
        handlers: {},
        addEventListener(type, fn) { (this.handlers[type] ??= []).push(fn); },
        fire(type, extra = {}) {
            const event = {defaultPrevented:false, preventDefault(){this.defaultPrevented=true;}, ...extra};
            for (const fn of this.handlers[type] ?? []) fn(event);
            return event;
        },
    });
    const form = target(); form.hasAttribute = () => native;
    const document = target(); document.querySelectorAll = () => [form];
    const stay=target(),leave=target(),modal=target();modal.setAttribute=()=>{};modal.querySelector=selector=>selector.includes('stay')?stay:leave;
    modal.showModal=()=>{modal.open=true;};modal.close=()=>{modal.open=false;modal.fire('close');};document.createElement=()=>modal;document.body={append(){}};
    const window = target(); window.location={assign(url){window.destination=url;}};
    vm.runInNewContext(fs.readFileSync('public/js/registration-unsaved.js', 'utf8'), {
        document, window, URL, queueMicrotask,
        location: new URL('https://pocketfinds.test/register/rider'),
    });
    return {form, document, window,modal,stay,leave};
}

test('warns after edits, keeps warning after failed submission, clears after saved', () => {
    const {form, window} = setup();
    assert.equal(window.fire('beforeunload').defaultPrevented, false);
    form.fire('input');
    assert.equal(window.fire('beforeunload').defaultPrevented, true);
    form.fire('submit', {defaultPrevented:true});
    assert.equal(window.fire('beforeunload').defaultPrevented, true);
    form.fire('registration:saved');
    assert.equal(window.fire('beforeunload').defaultPrevented, false);
});

test('file selection and camera capture mark registration as unsaved', () => {
    const {form, window} = setup();
    form.fire('change');
    assert.equal(window.fire('beforeunload').defaultPrevented, true);
    form.fire('registration:saved');
    form.fire('registration:dirty');
    assert.equal(window.fire('beforeunload').defaultPrevented, true);
});

test('cancel keeps the form protected and confirm allows leaving without a second prompt', () => {
    const {form, document, window,modal,stay,leave} = setup();
    form.fire('input');
    const link = {href:'https://pocketfinds.test/login', target:'', hasAttribute:()=>false};
    const event = {target:{closest:()=>link}};
    assert.equal(document.fire('click', event).defaultPrevented, true);
    assert.equal(window.fire('beforeunload').defaultPrevented, true);
    assert.equal(modal.open,true);stay.fire('click');assert.equal(modal.open,false);
    document.fire('click',event);leave.fire('click');
    assert.equal(window.destination,'https://pocketfinds.test/login');
    assert.equal(window.fire('beforeunload').defaultPrevented, false);
});

test('native forms suppress warning only when submission was allowed', async () => {
    const {form, window} = setup(true);
    form.fire('input');
    form.fire('submit', {defaultPrevented:true});
    await Promise.resolve();
    assert.equal(window.fire('beforeunload').defaultPrevented, true);
    form.fire('submit');
    await Promise.resolve();
    assert.equal(window.fire('beforeunload').defaultPrevented, false);
});
