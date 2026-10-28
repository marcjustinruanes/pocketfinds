const endpoint = 'http://127.0.0.1:9224';
const largeImage = process.argv[2];

async function pause(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }
async function json(url, options) { return (await fetch(url, options)).json(); }

const targets = await json(`${endpoint}/json`);
const target = targets.find(item => item.type === 'page');
if (!target) throw new Error('No debuggable page target was found.');
const ws = new WebSocket(target.webSocketDebuggerUrl);
let sequence = 0;
const pending = new Map();
ws.onmessage = event => {
  const msg = JSON.parse(event.data);
  if (msg.id && pending.has(msg.id)) {
    const { resolve, reject } = pending.get(msg.id);
    pending.delete(msg.id);
    msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
  }
};
await Promise.race([
  new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; ws.onclose = () => reject(new Error('DevTools socket closed')); }),
  new Promise((_, reject) => setTimeout(() => reject(new Error('DevTools socket timed out')), 5000))
]);
function send(method, params = {}) {
  const id = ++sequence;
  ws.send(JSON.stringify({ id, method, params }));
  return new Promise((resolve, reject) => pending.set(id, { resolve, reject }));
}
async function evaluate(expression, awaitPromise = true) {
  const result = await send('Runtime.evaluate', { expression, awaitPromise, returnByValue: true });
  if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
  return result.result.value;
}

await send('Page.enable');
await send('Runtime.enable');
await send('Page.navigate', { url: 'http://127.0.0.1:8000/register/rider' });
await pause(2500);

const initial = await evaluate(`(() => ({
  title: document.title,
  licenseExists: !!document.querySelector('#license_number'),
  licensePanelVisible: getComputedStyle(document.querySelector('#panel-2')).display !== 'none',
  ownVehicleVisible: getComputedStyle(document.querySelector('#ownVehicleSection')).display !== 'none',
  fileInputs: [...document.querySelectorAll('input[type=file]')].map(x => ({name:x.name, accept:x.accept, display:getComputedStyle(x).display}))
}))()`);

const revealed = await evaluate(`(() => {
  setStep(5, 2);
  const licensePanelVisible = getComputedStyle(document.querySelector('#panel-2')).display !== 'none';
  const licenseNumberVisible = document.querySelector('#license_number').offsetParent !== null;
  const licenseUploadPresent = !!document.querySelector('#license_file');
  setStep(2, 7);
  document.querySelector('#vo_own').checked = true;
  onOwnershipChange();
  document.querySelector('#vt_two_wheels').checked = true;
  onVehicleTypeChange();
  return {
    licensePanelVisible,
    licenseNumberVisible,
    licenseUploadPresent,
    ownVehicleVisible: getComputedStyle(document.querySelector('#ownVehicleSection')).display !== 'none',
    plateVisible: document.querySelector('#plate_number').offsetParent !== null,
    orVisible: document.querySelector('#or_file').parentElement.offsetParent !== null,
    crVisible: document.querySelector('#cr_file').parentElement.offsetParent !== null
  };
})()`);

await evaluate(`startCamera(); true`);
await pause(1200);
const deniedCamera = await evaluate(`(() => ({
  cameraVisible: getComputedStyle(document.querySelector('#selfieCamera')).display !== 'none',
  errorVisible: getComputedStyle(document.querySelector('#selfieError')).display !== 'none',
  errorText: document.querySelector('#selfieError').textContent.trim(),
  settingsLink: !![...document.querySelectorAll('a,button')].find(x => /settings/i.test(x.textContent))
}))()`);

let oversized = { attempted: false };
if (largeImage) {
  const doc = await send('DOM.getDocument');
  const node = await send('DOM.querySelector', { nodeId: doc.root.nodeId, selector: '#license_file' });
  await send('DOM.setFileInputFiles', { nodeId: node.nodeId, files: [largeImage] });
  await evaluate(`document.querySelector('#license_file').dispatchEvent(new Event('change', {bubbles:true})); true`);
  await pause(3500);
  oversized = await evaluate(`(() => ({
    attempted: true,
    originalSelectedSize: document.querySelector('#license_file').files[0]?.size || 0,
    acceptedSize: typeof docBlobs !== 'undefined' && docBlobs.license ? docBlobs.license.size : 0,
    errorVisible: getComputedStyle(document.querySelector('#licenseError')).display !== 'none',
    errorText: document.querySelector('#licenseError').textContent.trim()
  }))()`);
}

console.log(JSON.stringify({ initial, revealed, deniedCamera, oversized }, null, 2));
await send('Page.close');
ws.close();
