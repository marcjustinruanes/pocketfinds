<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/bootstrap.php';
foreach(['Support','Buyer','BuyerWeb'] as $file)require_once dirname(__DIR__)."/$file.php";
use App\Models\{User,Product,BuyerAddress,CartItem,PaymentMethod,Order,Setting,GcashPurchase};
use App\Services\{BuyerCommerce,GcashWorkflow};
use App\Http\Controllers\{CommerceController,GcashPurchaseController};
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Support\Facades\{DB,Auth,Storage};
use Illuminate\Support\Str;
$checks=0;
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
 $quote=app(BuyerCommerce::class)->quote($buyer,[$cart->id],$address->id);
 $input=['items'=>[$cart->id],'payment_method'=>$method->id,'delivery_address_id'=>$address->id,'checkout_id'=>(string)Str::uuid(),'quote_hash'=>hash('sha256',json_encode($quote))];
 $native=new MobileBuyer(new Identity($buyer));
 $checkout=fn()=>$native->native($input,fn($unused,$r)=>app(CommerceController::class)->checkout($r));
 $result=$checkout();$stored=json_decode(DB::table('commerce_checkouts')->where('id',$input['checkout_id'])->value('result'),true);$purchase=GcashPurchase::findOrFail($stored['ids'][0]);$order=Order::where('gcash_purchase_id',$purchase->id)->firstOrFail();
 checkWebGcash($purchase->usesAdminReview() && $purchase->payment_state==='awaiting_payment' && !$order->platform_payment,'website checkout creates the shared admin-review purchase and order');
 checkWebGcash($purchase->instructions['name']==='GCash integration fixture' && (string)$purchase->instructions['amount']===(string)$purchase->amount_due,'payment details and exact amount come from website configuration and quote');
 checkWebGcash($product->fresh()->stock===19 && !CartItem::find($cart->id),'website checkout reserves stock and removes selected cart rows');
 checkWebGcash($checkout()===$result && $product->fresh()->stock===19,'website checkout retry creates no duplicate order or reservation');
 denyWebGcash(fn()=>app(GcashWorkflow::class)->assertOrderOperation($order,true),'seller handoff remains blocked before admin approval');
 // Render the real website order page using the same authenticated buyer.
 $request=Request::create('/buyer/orders?payment=gcash','GET');$request->setUserResolver(fn()=>$buyer);$session=new Illuminate\Session\Store('gcash-fixture',new Illuminate\Session\ArraySessionHandler(30));$session->start();$request->setLaravelSession($session);
 Illuminate\Support\Facades\View::share('errors',new Illuminate\Support\ViewErrorBag);
 Auth::setUser($buyer);$previous=app('request');app()->instance('request',$request);
 try{$html=app(App\Http\Controllers\BuyerController::class)->orders($request)->render();}finally{app()->instance('request',$previous);}
 checkWebGcash(str_contains($html,'order-payment-steps') && str_contains($html,'data-order-payment-proof') && str_contains($html,'data-payment-file-preview'),'actual website payment timeline, upload form and receipt preview render in buyer orders');
 checkWebGcash(str_contains($html,'GCash integration fixture') && !str_contains($html,'id="mobile-checkout"'),'buyer page uses website account UI without a native checkout handoff');
 $path=dirname(__DIR__,2).'/assets/brand/storefront.jpg';$submission=(string)Str::uuid();
 $upload=fn($id,$actor,$key)=> (new MobileBuyer(new Identity($actor)))->native(['submission_id'=>$key],function($unused,$r)use($id,$path){$r->files->set('receipt',new UploadedFile($path,'receipt.jpg',null,null,true));return app(GcashPurchaseController::class)->proof($r,$id,app(GcashWorkflow::class));});
 denyWebGcash(fn()=>$upload($purchase->id,$outsider,(string)Str::uuid()),'other roles cannot upload buyer payment evidence');
 $upload($purchase->id,$buyer,$submission);$upload($purchase->id,$buyer,$submission);$purchase->refresh();
 checkWebGcash($purchase->payment_state==='proof_submitted' && !$purchase->verified_at,'website receipt submission stays pending admin review');
 checkWebGcash(DB::table('gcash_proofs')->where('purchase_id',$purchase->id)->count()===1,'receipt retry creates exactly one private proof');
 $proof=DB::table('gcash_proofs')->where('purchase_id',$purchase->id)->first();checkWebGcash(Storage::disk('local')->exists($proof->path),'website private proof storage contains the uploaded receipt');
 denyWebGcash(fn()=>app(GcashWorkflow::class)->decide($buyer,$purchase->id,$purchase->revision,true,(string)$purchase->amount_due,null,'FIXTURE-'.$tag),'buyer cannot approve their own payment');
 app(GcashWorkflow::class)->decide($admin,$purchase->id,$purchase->revision,false,null,'Receipt unreadable. Please replace it.');$purchase->refresh();
 checkWebGcash($purchase->payment_state==='rejected','website rejection state is available to buyer payment UI');
 $upload($purchase->id,$buyer,(string)Str::uuid());$purchase->refresh();checkWebGcash($purchase->payment_state==='proof_submitted','website allows corrected receipt after rejection');
 app(GcashWorkflow::class)->decide($admin,$purchase->id,$purchase->revision,true,(string)$purchase->amount_due,null,'FIXTURE-'.$tag);$purchase->refresh();
 checkWebGcash($purchase->payment_state==='order_placed' && (int)$purchase->verified_by===$admin->id,'website admin approval records the verifier and shared payment state');
 app(GcashWorkflow::class)->assertOrderOperation($order->fresh(),true);checkWebGcash(true,'website fulfillment guard opens only after payment approval');
 echo "$checks website GCash integration checks passed.\n";
}finally{Auth::forgetUser();DB::rollBack();echo "All temporary buyers, orders, configuration, stock and receipt files rolled back.\n";}
