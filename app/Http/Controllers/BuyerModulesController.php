<?php

namespace App\Http\Controllers;

use App\Models\BuyerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\MediaLifecycle;
use App\Support\PhilippineMobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BuyerModulesController extends Controller
{
    private function actor(Request $r): User
    {
        $u=$r->user()->fresh();
        abort_unless($u->status==='approved' && in_array($u->account_type,['buyer','seller']),403);
        return $u;
    }

    public function photo(Request $r)
    {
        $u=$this->actor($r);
        $r->validate(['profile_picture'=>'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=6000,max_height=6000']);
        DB::transaction(function()use($u,$r){
            $locked=User::whereKey($u->id)->lockForUpdate()->firstOrFail(); $old=$locked->profile_picture;
            $path=app(MediaLifecycle::class)->store($r->file('profile_picture'),'avatars','profile_images');
            $locked->forceFill(['profile_picture'=>$path])->save();
            app(MediaLifecycle::class)->deleteAfterCommit($old,'profile_images');
        });
        return back()->with('success','Profile photo updated.');
    }

    public function address(Request $r, BuyerAddress $address)
    {
        $u=$this->actor($r); abort_unless($u->account_type==='buyer' && $address->buyer_id===$u->id,403);
        $r->merge(['contact_no'=>PhilippineMobile::normalize((string)$r->contact_no)]);
        $data=$r->validate([
            'label'=>'nullable|string|max:50','recipient_name'=>'required|string|max:150','contact_no'=>['required','regex:/^09[0-9]{9}$/D'],
            'region'=>'required|string|max:255','province'=>'required|string|max:150','municipality'=>'required|string|max:150',
            'barangay'=>'required|string|max:150','postal_code'=>['nullable','regex:/^[0-9]{4}$/D'],'house_no'=>'nullable|string|max:100','street'=>'nullable|string|max:150',
        ]);
        $address->update($data);
        return $r->expectsJson()?response()->json(['success'=>true]):back()->with('success','Delivery address updated. Existing orders keep their original address.');
    }

    public function defaultAddress(Request $r, BuyerAddress $address)
    {
        $u=$this->actor($r); abort_unless($u->account_type==='buyer' && $address->buyer_id===$u->id,403);
        DB::transaction(function()use($u,$address){
            User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            $selected=BuyerAddress::where('buyer_id',$u->id)->whereKey($address->id)->lockForUpdate()->firstOrFail();
            BuyerAddress::where('buyer_id',$u->id)->update(['is_default'=>false]); $selected->update(['is_default'=>true]);
        });
        return back()->with('success','Default delivery address updated.');
    }

    public function following(Request $r)
    {
        $u=$this->actor($r); abort_unless($u->account_type==='buyer',403);
        return redirect(route('buyer.account').'#following-shops');
    }

    public function categories(Request $r)
    {
        $u=$this->actor($r); abort_unless($u->account_type==='seller',403);
        return redirect()->route('seller.inventory');
    }

    public function saveCategory(Request $r)
    {
        $u=$this->actor($r); abort_unless($u->account_type==='seller',403);
        $r->merge(['name'=>trim((string)$r->name)]);
        $d=$r->validate(['id'=>'nullable|integer','name'=>'required|string|max:50','parent_id'=>'nullable|integer','is_visible'=>'nullable|boolean','sort_order'=>'nullable|integer|min:0|max:9999']);
        DB::transaction(function()use($u,$d){
            $hasPresentationFields=Schema::hasColumn('shop_categories','is_visible') && Schema::hasColumn('shop_categories','sort_order');
            User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            $id=$d['id']??null;
            if($id)abort_unless(DB::table('shop_categories')->where('seller_id',$u->id)->where('id',$id)->exists(),404);
            if(DB::table('shop_categories')->where('seller_id',$u->id)->whereRaw('LOWER(name) = ?',[mb_strtolower($d['name'])])->when($id,fn($q)=>$q->where('id','!=',$id))->exists())throw ValidationException::withMessages(['name'=>'You already have a category with this name.']);
            // Shop categories are managed as a flat list in Inventory.
            $values=['name'=>$d['name'],'parent_id'=>null,'updated_at'=>now()];
            if ($hasPresentationFields) {
                $values['is_visible']=(bool)($d['is_visible']??true);
                $values['sort_order']=(int)($d['sort_order']??9999);
            }
            if($id)DB::table('shop_categories')->where('id',$id)->update($values);
            else {
                if ($hasPresentationFields) $values['sort_order'] = (int) (DB::table('shop_categories')->where('seller_id',$u->id)->max('sort_order') ?? -1) + 1;
                DB::table('shop_categories')->insert($values+['seller_id'=>$u->id,'created_at'=>now()]);
            }
        });
        return back()->with('success','Shop category saved.');
    }

    public function deleteCategory(Request $r, int $category)
    {
        $u=$this->actor($r); abort_unless($u->account_type==='seller',403);
        DB::transaction(function()use($u,$category){
            User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            abort_unless(DB::table('shop_categories')->where('id',$category)->where('seller_id',$u->id)->exists(),404);
            $ids=collect([$category]);
            DB::table('shop_categories')->where('seller_id',$u->id)->where('parent_id',$category)->update(['parent_id'=>null]);
            DB::table('shop_category_products')->whereIn('shop_category_id',$ids)->delete(); DB::table('shop_categories')->whereIn('id',$ids)->delete();
        });
        return back()->with('success','Category removed. Products remain available under their other categories or Uncategorized.');
    }

    public function assignCategories(Request $r, Product $product)
    {
        $u=$this->actor($r); abort_unless($u->account_type==='seller' && $product->seller_id===$u->id,403);
        $d=$r->validate(['categories'=>'nullable|array|max:3','categories.*'=>'required|integer|distinct']);
        DB::transaction(function()use($u,$product,$d){
            User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            $ids=$d['categories']??[];
            if(DB::table('shop_categories')->where('seller_id',$u->id)->whereIn('id',$ids)->count()!==count($ids))abort(403);
            DB::table('shop_category_products')->where('product_id',$product->id)->delete();
            foreach($ids as $id)DB::table('shop_category_products')->insert(['shop_category_id'=>$id,'product_id'=>$product->id]);
        });
        return back()->with('success','Product categories updated.');
    }

    public function refunds(Request $r)
    {
        $u=$this->actor($r);
        if($u->account_type==='seller')return redirect()->route('seller.orders',['status'=>'refunds']);
        $refunds=DB::table('cancellation_refunds as r')->join('orders as o','o.id','=','r.order_id')->where('r.'.($u->account_type==='buyer'?'buyer_id':'seller_id'),$u->id)->select('r.*','o.order_number')->orderByDesc('r.created_at')->get();
        return view('account.cancellation-refunds',compact('refunds','u'));
    }

    public function completeRefund(Request $r, int $refund)
    {
        $u=$r->user()->fresh();
        $admin=(bool)$u->is_admin;
        abort_unless($u->status==='approved' && ($admin||($u->account_type==='seller'&&!$u->is_admin)),403);
        $d=$r->validate(['repayment_reference'=>['nullable','string','min:6','max:100','regex:/^[A-Za-z0-9-]+$/D'],'confirm_repaid'=>'required|accepted']);
        $purchase = DB::transaction(function()use($r,$u,$refund,$d){
            $row=DB::table('cancellation_refunds')->where('id',$refund)->lockForUpdate()->firstOrFail();
            $order=Order::findOrFail($row->order_id);
            $platform=$order->gcashPurchase?->usesAdminReview();
            if(!$platform && $u->account_type==='seller' && (int)$row->seller_id!==(int)$u->id)abort(404);
            abort_unless($platform?(bool)$u->is_admin:($u->account_type==='seller'&&(int)$row->seller_id===(int)$u->id),403);
            if($row->status==='completed')return $order->gcashPurchase;
            abort_unless($order->status==='cancelled',409,'Only cancelled orders can receive a cancellation refund.');
            $reference = $platform ? null : $r->validate(['repayment_reference'=>['required','string','min:6','max:100','regex:/^[A-Za-z0-9-]+$/D']])['repayment_reference'];
            if($platform) {
                $r->validate(['refund_proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
                $file=$r->file('refund_proof');
                $path=app(MediaLifecycle::class)->store($file,'gcash-proofs','local');
                DB::table('gcash_purchase_history')->insert(['purchase_id'=>$order->gcash_purchase_id,'actor_id'=>$u->id,'event'=>'admin_cancellation_refund_proof','before'=>json_encode(['path'=>$path,'mime'=>$file->getMimeType(),'refund_id'=>$row->id]),'after'=>'{}','reason'=>'Admin returned the cancelled order payment to the original GCash payer.','created_at'=>now()]);
            }
            if($reference && DB::table('cancellation_refunds')->where('repayment_reference',$reference)->where('id','!=',$refund)->exists())throw ValidationException::withMessages(['repayment_reference'=>'This repayment reference is already recorded.']);
            DB::table('cancellation_refunds')->where('id',$refund)->update(['status'=>'completed','repayment_reference'=>$reference,'completed_at'=>now(),'updated_at'=>now()]);
            Order::notifyUser($row->buyer_id,'Cancellation refund repaid',($platform?'The platform admin':'The seller').' recorded your ₱'.number_format($row->amount,2).' repayment.'.($reference?' Reference: '.$reference:' Proof of returned payment is available in your order.'), 'order_status',$row->order_id);
            if($platform) Order::notifyUser($row->seller_id,'Cancellation refund completed','Admin returned the payment for cancelled order '.$order->order_number.'.','order_status',$row->order_id);
            return $order->gcashPurchase;
        });
        if($r->expectsJson()) return response()->json(['purchase'=>$purchase,'message'=>'Payment returned. The buyer has been notified.']);
        return back()->with('success','Completed repayment recorded.');
    }
}
