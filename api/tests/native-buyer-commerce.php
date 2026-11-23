<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/bootstrap.php';
foreach(['Support','Buyer','BuyerWeb','OrderDetails'] as $file)require_once dirname(__DIR__)."/$file.php";
use App\Models\{User,Product,BuyerAddress,CartItem,PaymentMethod,Order,Setting,GcashPurchase};
use App\Services\{BuyerCommerce,GcashWorkflow};
use App\Http\Controllers\{CommerceController,GcashPurchaseController};
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Support\Facades\{DB,Auth,Storage};
use Illuminate\Support\Str;
$checks=0;$nativeStage=null;
require_once dirname(__DIR__).'/Gcash.php';
require_once dirname(__DIR__).'/Media.php';
require_once dirname(__DIR__).'/Actions.php';
require_once dirname(__DIR__).'/Queries.php';
require_once dirname(__DIR__).'/Commerce.php';
function checkWebGcash(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function denyWebGcash(Closure $action,string $label):void{try{$action();}catch(ApiError|Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){checkWebGcash(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
DB::beginTransaction();
try {
 checkWebGcash(get_class(app(GcashWorkflow::class))===GcashWorkflow::class,'app uses the actual website GCash service');
 $tag=Str::lower(Str::random(10));
 $make=function($role)use($tag){$u=new User;$u->forceFill(['email'=>$role.'-'.$tag.'@example.invalid','username'=>$role.'_'.$tag,'given_names'=>'Web GCash','last_name'=>'Fixture','password'=>'Testing-12345!','account_type'=>$role,'is_admin'=>$role==='admin','status'=>'approved','email_verified_at'=>now(),'province'=>'Laguna','municipality'=>'Famy','barangay'=>'Test','region'=>'Region IV-A','postal_code'=>'4021','contact_no'=>'09123456789','house_no'=>'1','street'=>'Test']);$u->save();return $u;};
 $buyer=$make('buyer');$admin=$make('admin');$outsider=$make('seller');
 Setting::current()->update(['platform_gcash_name'=>'GCash integration fixture','platform_gcash_number'=>'09123456789']);
 $product=Product::where('status','active')->sellerApproved()->firstOrFail()->replicate();$product->id=(string)Str::uuid();$product->forceFill(['name'=>'Web GCash temporary item','sku'=>'TEST-'.Str::random(14),'variations'=>null,'variant_axes'=>null,'stock'=>20,'price'=>100,'discount_price'=>null,'weight_grams'=>100]);$product->save();
 $address=new BuyerAddress;$address->forceFill(['buyer_id'=>$buyer->id,'label'=>'Test','recipient_name'=>'Web GCash Fixture','contact_no'=>'09123456789','province'=>'Laguna','municipality'=>'Famy','barangay'=>'Test','region'=>'Region IV-A','postal_code'=>'4021','house_no'=>'1','street'=>'Test','is_default'=>true]);$address->save();
 $cart=CartItem::create(['buyer_id'=>$buyer->id,'product_id'=>$product->id,'qty'=>1,'price'=>100,'variation_group'=>'','variation_value'=>'','name'=>$product->name,'img'=>'','seller'=>$product->seller->business_name?:'Fixture store','seller_slug'=>$product->seller->username]);
 $method=PaymentMethod::forBuyerCheckout()->get()->first(fn($m)=>$m->buyerCheckoutKind()==='gcash');if(!$method)throw new RuntimeException('An active GCash method is required.');
 // Exercise native cart transport with the website's actual Color + Size SKU schema.
 $variant=$product->replicate();$variant->id=(string)Str::uuid();$variant->sku='TEST-'.Str::random(14);
 $variant->variations=[['name'=>'Options','type'=>'combinations','axes'=>[
   ['name'=>'Color','options'=>[['value'=>'Red'],['value'=>'Blue']]],
   ['name'=>'Size','options'=>[['value'=>'M'],['value'=>'L']]],
 ],'options'=>[
   ['value'=>'Red / M','attributes'=>['Color'=>'Red','Size'=>'M'],'stock'=>3,'price'=>125],
   ['value'=>'Red / L','attributes'=>['Color'=>'Red','Size'=>'L'],'stock'=>0,'price'=>125],
   ['value'=>'Blue / M','attributes'=>['Color'=>'Blue','Size'=>'M'],'stock'=>0,'price'=>150],
   ['value'=>'Blue / L','attributes'=>['Color'=>'Blue','Size'=>'L'],'stock'=>0,'price'=>150],
 ]]];$variant->stock=3;$variant->save();
 $cartApi=new MobileCart(new Identity($buyer));
 $cartInput=['product_id'=>$variant->id,'quantity'=>2,'group'=>'Options','value'=>'Red / M','request_id'=>(string)Str::uuid()];
 $variantRow=$cartApi->handle($cartInput);
 checkWebGcash($variantRow['quantity']===2 && $variantRow['price']===125.0,'native cart resolves the website color-and-size SKU price');
 checkWebGcash($cartApi->handle($cartInput)==$variantRow && (int)CartItem::findOrFail($variantRow['id'])->qty===2,'cart retry does not add the same selection twice');
 $queries=new MobileQueries(new Identity($buyer));
 $update=['table'=>'cart_items','action'=>'update','filters'=>[['column'=>'id','op'=>'=','value'=>$variantRow['id']]],'data'=>['qty'=>3]];
 $queries->handle($update);
 checkWebGcash((int)CartItem::findOrFail($variantRow['id'])->qty===3,'cart quantity update persists to the buyer database row');
 denyWebGcash(fn()=>$queries->handle(array_replace($update,['data'=>['qty'=>4]])),'cart quantity update rejects inventory overflow');
 denyWebGcash(fn()=>$queries->handle(array_replace($update,['data'=>['qty'=>0]])),'cart quantity update rejects zero quantity');
 $queries->handle(array_replace($update,['data'=>['qty'=>2]]));
 $removable=CartItem::findOrFail($variantRow['id'])->replicate();$removable->id=(string)Str::uuid();$removable->variation_value='Red / L';$removable->save();
 $queries->handle(['table'=>'cart_items','action'=>'delete','filters'=>[['column'=>'id','op'=>'in','value'=>[$removable->id]]]]);
 checkWebGcash(!CartItem::find($removable->id) && CartItem::find($variantRow['id'])!==null,'bulk removal deletes only requested buyer cart rows');
 denyWebGcash(fn()=>$cartApi->handle(array_replace($cartInput,['value'=>'Blue / L','request_id'=>(string)Str::uuid()])),'out-of-stock combination is rejected');
 denyWebGcash(fn()=>$cartApi->handle(array_replace($cartInput,['value'=>'Red / XL','request_id'=>(string)Str::uuid()])),'nonexistent combination is rejected');
 denyWebGcash(fn()=>$cartApi->handle(array_replace($cartInput,['group'=>'Color','value'=>'Red','request_id'=>(string)Str::uuid()])),'a color without its size cannot bypass combination validation');
 $variantQuote=app(BuyerCommerce::class)->quote($buyer,[$variantRow['id']],$address->id);
 checkWebGcash((float)array_values($variantQuote['orders'])[0]['subtotal']===250.0,'website quote accepts the exact native cart combination and price');
 $cod=PaymentMethod::forBuyerCheckout()->get()->first(fn($m)=>$m->buyerCheckoutKind()==='cod');
 if(!$cod)throw new RuntimeException('An active COD method is required.');
 $variantTransport=new NativeBuyerCommerce(new Identity($buyer));
 $variantSelection=['cart_ids'=>[$variantRow['id']],'delivery_address_id'=>$address->id];
 $variantPreview=$variantTransport->handle('quote',$variantSelection);
 $codPayload=$variantSelection+['checkout_id'=>(string)Str::uuid(),'payment_method_id'=>$cod->id,'quote_hash'=>$variantPreview['quote_hash']];
 denyWebGcash(fn()=>$variantTransport->handle('checkout',array_replace($codPayload,['quote_hash'=>str_repeat('0',64)])),'changed checkout summary requires review');
 $codResult=$variantTransport->handle('checkout',$codPayload);
 checkWebGcash(count($codResult)===1 && !$codResult[0]['payment_pending'] && $variant->fresh()->availableStock('Options','Red / M')===1,'native COD checkout reserves only the selected color-size stock');
 checkWebGcash($variantTransport->handle('checkout',$codPayload)===$codResult && $variant->fresh()->availableStock('Options','Red / M')===1,'COD retry does not duplicate stock reservation');
 $quote=app(BuyerCommerce::class)->quote($buyer,[$cart->id],$address->id);
 $input=['items'=>[$cart->id],'payment_method'=>$method->id,'delivery_address_id'=>$address->id,'checkout_id'=>(string)Str::uuid(),'quote_hash'=>hash('sha256',json_encode($quote))];
 $native=new MobileBuyer(new Identity($buyer));
 $transport=new NativeBuyerCommerce(new Identity($buyer));
 $selection=['cart_ids'=>[$cart->id],'delivery_address_id'=>$address->id,'voucher_codes'=>[]];
 $nativeQuote=$transport->handle('quote',$selection);
 checkWebGcash($nativeQuote['total']===$quote['total'],'native quote matches website pricing');
 $payload=$selection+['checkout_id'=>$input['checkout_id'],'payment_method_id'=>$method->id,'quote_hash'=>$nativeQuote['quote_hash']];
 $checkout=fn()=>$transport->handle('checkout',$payload);
 $result=$checkout();$stored=json_decode(DB::table('commerce_checkouts')->where('id',$input['checkout_id'])->value('result'),true);$purchase=GcashPurchase::findOrFail($stored['ids'][0]);$order=Order::where('gcash_purchase_id',$purchase->id)->firstOrFail();
 checkWebGcash($purchase->usesAdminReview() && $purchase->payment_state==='awaiting_payment' && !$order->platform_payment,'website checkout creates the shared admin-review purchase and order');
 checkWebGcash($purchase->instructions['name']==='GCash integration fixture' && (string)$purchase->instructions['amount']===(string)$purchase->amount_due,'payment details and exact amount come from website configuration and quote');
 checkWebGcash($product->fresh()->stock===19 && !CartItem::find($cart->id),'website checkout reserves stock and removes selected cart rows');
 checkWebGcash($checkout()===$result && $product->fresh()->stock===19,'website checkout retry creates no duplicate order or reservation');
 denyWebGcash(fn()=>app(GcashWorkflow::class)->assertOrderOperation($order,true),'seller handoff remains blocked before admin approval');
 $path=dirname(__DIR__,2).'/assets/brand/storefront.jpg';$submission=(string)Str::uuid();
 $upload=fn($id,$actor,$key)=> (new MobileBuyer(new Identity($actor)))->native(['submission_id'=>$key],function($unused,$r)use($id,$path){$r->files->set('receipt',new UploadedFile($path,'receipt.jpg',null,null,true));return app(GcashPurchaseController::class)->proof($r,$id,app(GcashWorkflow::class));});
 denyWebGcash(fn()=>$upload($purchase->id,$outsider,(string)Str::uuid()),'other roles cannot upload buyer payment evidence');
 $upload($purchase->id,$buyer,$submission);$upload($purchase->id,$buyer,$submission);$purchase->refresh();
 checkWebGcash($purchase->payment_state==='proof_submitted' && !$purchase->verified_at,'website receipt submission stays pending admin review');
 $orderDetails=MobileOrderDetails::read($order->fresh(),$buyer->id);
 checkWebGcash((float)$orderDetails['payment']['paid']===0.0 && $orderDetails['payment']['reference']===null,'submitted proof never appears as verified money in order details');
 checkWebGcash(DB::table('gcash_proofs')->where('purchase_id',$purchase->id)->count()===1,'receipt retry creates exactly one private proof');
 $proof=DB::table('gcash_proofs')->where('purchase_id',$purchase->id)->first();checkWebGcash(Storage::disk('local')->exists($proof->path),'website private proof storage contains the uploaded receipt');
 denyWebGcash(fn()=>app(GcashWorkflow::class)->decide($buyer,$purchase->id,$purchase->revision,true,(string)$purchase->amount_due,null,'FIXTURE-'.$tag),'buyer cannot approve their own payment');
 app(GcashWorkflow::class)->decide($admin,$purchase->id,$purchase->revision,false,null,'Receipt unreadable. Please replace it.');$purchase->refresh();
 checkWebGcash($purchase->payment_state==='rejected','website rejection state is available to buyer payment UI');
 $nativePath=$buyer->id.'/'.Str::uuid().'.jpg';$nativeStage='payment-proofs/'.$nativePath;Storage::disk('local')->put($nativeStage,file_get_contents($path));
 $submit=['order_id'=>$order->id,'path'=>$nativePath,'submission_id'=>(string)Str::uuid()];
 $paymentApi=new MobileGcash(new Identity($buyer));$paymentApi->handle('order-proof',$submit);$paymentApi->handle('order-proof',$submit);
 $purchase->refresh();checkWebGcash($purchase->payment_state==='proof_submitted','native receipt resubmission uses website proof handling without a typed reference');
 app(GcashWorkflow::class)->decide($admin,$purchase->id,$purchase->revision,true,(string)$purchase->amount_due,null,'FIXTURE-'.$tag);$purchase->refresh();
 checkWebGcash($purchase->payment_state==='order_placed' && (int)$purchase->verified_by===$admin->id,'website admin approval records the verifier and shared payment state');
 $orderDetails=MobileOrderDetails::read($order->fresh(),$buyer->id);
 checkWebGcash((float)$orderDetails['payment']['paid']===(float)$purchase->verified_amount && $orderDetails['payment']['outstanding']===0.0 && $orderDetails['payment']['reference']===strtoupper('FIXTURE-'.$tag) && $orderDetails['payment']['verified_at']!==null,'verified GCash amount reference and date come from website purchase');
 app(GcashWorkflow::class)->assertOrderOperation($order->fresh(),true);checkWebGcash(true,'website fulfillment guard opens only after payment approval');
 $mobile=new MobileGcash(new Identity($buyer));$detail=$mobile->handle('order-payment',['order_id'=>$order->id]);
 checkWebGcash($detail['status']==='paid' && $detail['payment_state']==='order_placed' && $detail['reference_required']===false,'native payment screen reads website approval without adding a reference requirement');
 checkWebGcash($detail['configuration']['account_name']==='GCash integration fixture','native screen uses the immutable website payment account snapshot');
 denyWebGcash(fn()=>(new MobileGcash(new Identity($outsider)))->handle('order-payment',['order_id'=>$order->id]),'another role cannot read buyer payment details');
 $reviewApi=new MobileActions(new Identity($buyer));
 checkWebGcash($reviewApi->handle('reviewable_product_order',['p_product_id'=>$product->id])['order_id']===null,'uncompleted purchases do not expose a review action');
 denyWebGcash(fn()=>$reviewApi->handle('submit_review',['p_order_id'=>$order->id,'p_product_id'=>$product->id,'p_rating'=>5]),'uncompleted purchases cannot submit reviews');
 DB::table('orders')->where('id',$order->id)->update(['status'=>'completed']);
 checkWebGcash($reviewApi->handle('reviewable_product_order',['p_product_id'=>$product->id])['order_id']===$order->id,'completed purchase exposes an eligible product review');
 $otherBuyer=$buyer->replicate();$otherBuyer->email='other-'.$tag.'@example.invalid';$otherBuyer->username='other_'.$tag;$otherBuyer->save();
 $otherReviewApi=new MobileActions(new Identity($otherBuyer));
 checkWebGcash($otherReviewApi->handle('reviewable_product_order',['p_product_id'=>$product->id])['order_id']===null,'buyer without purchase history has no review action');
 denyWebGcash(fn()=>$otherReviewApi->handle('submit_review',['p_order_id'=>$order->id,'p_product_id'=>$product->id,'p_rating'=>5]),'buyer cannot review someone else purchase');
 denyWebGcash(fn()=>$reviewApi->handle('submit_review',['p_order_id'=>$order->id,'p_product_id'=>$variant->id,'p_rating'=>5]),'buyer cannot review a product missing from the completed order');

 $review=(new MobileActions(new Identity($buyer)))->handle('submit_review',['p_order_id'=>$order->id,'p_product_id'=>$product->id,'p_rating'=>5,'p_comment'=>'Review without rewards.']);
 checkWebGcash($review[0]['saved']===true && DB::table('reviews')->where('buyer_id',$buyer->id)->where('product_id',$product->id)->exists(),'reviews save normally without the retired reward ledger');
 checkWebGcash($reviewApi->handle('reviewable_product_order',['p_product_id'=>$product->id])['order_id']===null,'already reviewed purchases no longer offer another review');
 checkWebGcash(!Illuminate\Support\Facades\Schema::hasTable('coin_transactions') && !Illuminate\Support\Facades\Schema::hasColumn('orders','coins_used'),'retired reward schema stays absent after checkout and review');
 echo "$checks native buyer commerce checks passed.\n";
}finally{Auth::forgetUser();DB::rollBack();if($nativeStage)Storage::disk('local')->delete($nativeStage);echo "All temporary buyers, orders, configuration, stock and receipt files rolled back.\n";}
