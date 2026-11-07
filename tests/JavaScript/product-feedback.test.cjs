const {test}=require('node:test');
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {runInNewContext}=require('node:vm');
const source=readFileSync('public/js/buyer.js','utf8');
const helper=source.slice(source.indexOf('function showProductFeedback('),source.indexOf('function showToast('));
function fixture(reduced=false){
 const nodes=[],timers=[];
 const document={createElement(tag){const node={tag,style:{},children:[],attributes:{},animations:[],append(...children){this.children.push(...children);},setAttribute(key,value){this.attributes[key]=value;},remove(){this.removed=true;},animate(frames,options){this.animations.push({frames,options});return {};}};nodes.push(node);return node;},getElementById(id){return nodes.find(node=>node.id===id&&!node.removed);},body:{append(node){node.appended=true;}}};
 const context={document,matchMedia:()=>({matches:reduced}),setTimeout:fn=>timers.push(fn)};
 runInNewContext(helper,context);
 return {context,nodes,timers,document};
}
test('cart feedback includes the product image and name with wishlist motion',()=>{
 const f=fixture();
 f.context.showProductFeedback({id:'cartFeedbackCard',title:'Added to cart',name:'Example product',image:'/product.jpg',origin:{getBoundingClientRect:()=>({left:20,top:40})},target:{getBoundingClientRect:()=>({left:100,top:10})},animate:true});
 const card=f.document.getElementById('cartFeedbackCard');
 assert.equal(card.attributes.role,'status');
 assert.equal(card.children[0].src,'/product.jpg');
 assert.equal(card.children[1].children[0].textContent,'Added to cart');
 assert.equal(card.children[1].children[1].textContent,'Example product');
 assert.equal(card.animations[0].options.duration,220);
 assert.equal(f.nodes.find(node=>node.tag==='img'&&node.animations.length).animations[0].options.duration,650);
 f.timers.forEach(fn=>fn());assert.equal(card.removed,true);
});
test('reduced motion still shows the notification and replaces the previous card',()=>{
 const f=fixture(true);
 const details={title:'Added',name:'Product',image:'/product.jpg',animate:true};
 f.context.showProductFeedback({...details,id:'wishlistFeedbackCard'});
 const previous=f.document.getElementById('wishlistFeedbackCard');
 f.context.showProductFeedback({...details,id:'cartFeedbackCard'});
 assert.equal(previous.removed,true);
 assert.ok(f.document.getElementById('cartFeedbackCard'));
 assert.equal(f.nodes.reduce((total,node)=>total+node.animations.length,0),0);
});
