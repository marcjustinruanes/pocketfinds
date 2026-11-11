const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const context={module:{exports:{}}};
vm.runInNewContext(fs.readFileSync('public/js/nested-product-variants.js','utf8'),context);
const {build,NONE}=context.module.exports;
const fields=['Size','Weight','Dimensions'];
const child=(size,stock,extra={})=>({attributes:{Size:size,...extra},stock,price:''});
test('colors have independent size lists instead of every possible pair',()=>{
 const [group]=build('Color',[
  {value:'Beige',color:'#CCBB99',children:[child('S',3),child('M',2),child('L',4)]},
  {value:'Gray',children:[child('S',1),child('M',2),child('L',3),child('XL',4)]},
 ],fields);
 assert.equal(group.selective,true);assert.equal(group.options.length,7);
 assert.ok(!group.options.some(o=>o.attributes.Color==='Beige'&&o.attributes.Size==='XL'));
 assert.equal(group.options.reduce((n,o)=>n+o.stock,0),19);
 assert.equal(group.axes[0].options[0].color,'#CCBB99');
});
test('one color can have no size while another has weight and dimensions',()=>{
 const [group]=build('Color',[{value:'White',stock:4},{value:'Black',children:[child('M',3,{Weight:'250 g',Dimensions:'20 x 10 x 5 cm'})]}],fields);
 assert.equal(group.axes.length,4);assert.equal(group.options[0].attributes.Size,NONE);
 assert.equal(group.options[0].stock,4);assert.equal(group.options[1].attributes.Weight,'250 g');
});
test('single options keep original inventory identity and images',()=>{
 const [group]=build('Color',[{value:'White',stock:4,price:123,existing_image:'saved.png',children:[]}],fields);
 assert.equal(group.name,'Color');assert.equal(group.options[0].value,'White');assert.equal(group.options[0].existing_image,'saved.png');assert.equal(group.options[0].price,123);
});
test('existing combination order and stock values survive regrouping',()=>{
 const [group]=build('Color',[{value:'White',children:[child('S',7)]}],fields,['Size','Color']);
 assert.equal(group.options[0].value,'S / White');assert.equal(group.options[0].stock,7);
});
test('invalid or duplicate nested entries cannot silently overwrite inventory',()=>{
 assert.throws(()=>build('Color',[{value:'White',children:[child('',1)]}],fields),/Enter a size/);
 assert.throws(()=>build('Color',[{value:'White',children:[child('S',1),child('S',2)]}],fields),/unique/);
});
