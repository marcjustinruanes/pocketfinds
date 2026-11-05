const {test}=require('node:test'),assert=require('node:assert/strict');
const vm=require('node:vm'),fs=require('node:fs'),path=require('node:path');
const context={module:{exports:{}}};vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/license-number-input.js'),'utf8'),context);const {format}=context.module.exports;
test('license number formats partial typing and pasted numbers',()=>{
 for(const [input,expected]of [['n','N'],['n01','N01'],['n012','N01-2'],['n0123','N01-23'],['n0123456789','N01-23-456789'],['n01 23 456789','N01-23-456789'],['N01-23-456789','N01-23-456789'],['ab','A']])assert.equal(format(input),expected);
});
test('license formatter excludes invalid characters and excess digits',()=>{
 assert.equal(format('12345'),'');assert.equal(format('N!01!234567890123'),'N01-23-456789');assert.equal(format(''),'');
});
