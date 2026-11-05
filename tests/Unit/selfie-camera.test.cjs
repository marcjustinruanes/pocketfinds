const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function camera() {
    const listeners = {};
    const track = {readyState:'live',enabled:true,muted:false,addEventListener(name,fn){listeners[name]=fn;},stop(){this.readyState='ended';}};
    const stream = {getVideoTracks:()=>[track],getTracks:()=>[track]};
    const video = {srcObject:null,readyState:0,paused:true,videoWidth:0,videoHeight:0,addEventListener(){}};
    const button = {disabled:false}; let draws=0, blobs=0, transform;
    const canvas = {getContext:()=>({setTransform(...args){transform=args;},drawImage(){draws++;}}),toBlob(){blobs++;}};
    const nodes = {selfieVideo:video,selfieCanvas:canvas,selfieIdle:{style:{}},selfiePreview:{style:{}},selfieCamera:{style:{}},selfieError:{style:{}}};
    let blank=false;
    const sample={getContext:()=>({drawImage(){},getImageData(){return {data:Uint8ClampedArray.from({length:32*24*4},(_,i)=>i%4===3?255:blank?128:(Math.floor(i/4)%2)*200)};}})};
    let resolveCamera;
    const context = vm.createContext({document:{getElementById:id=>nodes[id],querySelector:()=>button,createElement:()=>sample},setInterval:()=>1,clearInterval(){},navigator:{mediaDevices:{getUserMedia:()=>new Promise(resolve=>resolveCamera=resolve)}}});
    const source=fs.readFileSync(require('node:path').join(__dirname,'../../public/js/register.js'),'utf8');
    vm.runInContext(source.slice(source.indexOf('let selfieBlob = null;'),source.indexOf('function resetIdentityUploads()')),context);
    const run=code=>vm.runInContext(code,context);
    const start=async()=>{run('startCamera()');resolveCamera(stream);await Promise.resolve();};
    return {run,start,video,button,track,listeners,stream,setBlank:value=>blank=value,resolve:()=>resolveCamera(stream),counts:()=>({draws,blobs,transform})};
}

test('capture is disabled with the camera off and cannot create an empty selfie',()=>{
    const c=camera();assert.equal(c.button.disabled,true);c.run('snapSelfie()');assert.equal(c.counts().draws,0);
});
test('capture waits for live video frames and disables when the camera is muted or ended',async()=>{
    const c=camera();await c.start();assert.equal(c.button.disabled,true);
    Object.assign(c.video,{readyState:2,paused:false,videoWidth:640,videoHeight:480});c.run('updateSelfieCapture()');assert.equal(c.button.disabled,false);
    c.track.muted=true;c.listeners.mute();assert.equal(c.button.disabled,true);c.run('snapSelfie()');assert.equal(c.counts().draws,0);
    c.track.muted=false;c.track.readyState='ended';c.listeners.ended();assert.equal(c.button.disabled,true);
});
test('capture applies the same horizontal correction as the preview and disables after stopping',async()=>{
    const c=camera();await c.start();Object.assign(c.video,{readyState:2,paused:false,videoWidth:640,videoHeight:480});
    c.run('snapSelfie()');assert.equal(c.counts().draws,1);assert.equal(c.counts().blobs,1);
    assert.deepEqual(c.counts().transform,[-1,0,0,1,640,0]);assert.equal(c.button.disabled,true);assert.equal(c.video.srcObject,null);
});
test('leaving the step while permission is pending stops a late camera stream',async()=>{
    const c=camera();c.run('startCamera();stopCamera()');c.resolve();await Promise.resolve();
    assert.equal(c.track.readyState,'ended');assert.equal(c.video.srcObject,null);assert.equal(c.button.disabled,true);
});
test('a camera-off privacy screen cannot be captured even when its media track is live',async()=>{
    const c=camera();await c.start();Object.assign(c.video,{readyState:2,paused:false,videoWidth:640,videoHeight:480});
    c.setBlank(true);c.run('updateSelfieCapture();snapSelfie()');assert.equal(c.button.disabled,true);assert.equal(c.counts().blobs,0);
    c.setBlank(false);c.run('updateSelfieCapture()');assert.equal(c.button.disabled,false);
});
