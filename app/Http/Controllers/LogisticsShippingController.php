<?php

namespace App\Http\Controllers;

use App\Services\LogisticsFinance;
use App\Services\LogisticsShipping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LogisticsShippingController extends LogisticsController
{
    public function sellerIndex(Request $request, LogisticsShipping $shipping)
    {
        abort_unless($shipping->installed(), 503);
        app(\App\Services\GcashWorkflow::class)->eligible($request->user(), 'seller');
        $obligations = DB::table('logistics_shipping_obligations')->where('seller_id', $request->user()->id)->orderByDesc('created_at')->paginate(20);
        return response()->view('seller.shipping', compact('obligations'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $id, LogisticsShipping $shipping)
    {
        abort_unless($shipping->installed(), 503);
        $ob = DB::table('logistics_shipping_obligations')->where('id', $id)->firstOrFail();
        $actor = $shipping->participant($request->user(), $ob);
        $seller = $actor->account_type === 'seller';
        $payments = DB::table('logistics_shipping_payments')->where('obligation_id', $id)->orderByDesc('created_at')->get();
        $history = DB::table('logistics_financial_events')->where('company_name', $ob->company_name)->where('subject_type', 'shipping_obligation')->where('subject_id', $id)->orderBy('id')->get();
        $rate = DB::table('logistics_shipping_rates')->where('id', $ob->rate_id)->firstOrFail();
        $shipment = \App\Models\Shipment::findOrFail($ob->shipment_id);
        return response()->view('logistics.shipping-show', array_merge($seller ? [] : $this->sidebarCounts(), compact('ob', 'seller', 'payments', 'history', 'rate', 'shipment')))
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function shippingAccount(Request $request, LogisticsShipping $shipping)
    {
        abort_unless($shipping->installed(), 503);
        $shipping->configure($request->user(), $request->all());
        return back()->with('success', 'Company receiving account saved. Existing payment instructions were preserved.');
    }

    public function rate(Request $request, LogisticsShipping $shipping)
    {
        abort_unless($shipping->installed(), 503);
        $shipping->publish($request->user(), $request->all());
        return back()->with('success', 'Shipping rate approved. Existing shipment obligations retain their original rate.');
    }

    public function submit(Request $request, string $id, LogisticsShipping $shipping)
    {
        abort_unless($shipping->installed(), 503);
        $request->validate(['proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        $shipping->submit($request->user(), $id, $request->all(), $request->file('proof'));
        return back()->with('success', 'Shipping payment submitted. Only your carrier’s Logistics Admin can verify receipt.');
    }

    public function review(Request $request, string $id, LogisticsShipping $shipping)
    {
        abort_unless($shipping->installed(), 503);
        $shipping->review($request->user(), $id, $request->all());
        return back()->with('success', 'Shipping payment review recorded.');
    }

    public function proof(Request $request, string $id, LogisticsShipping $shipping)
    {
        abort_unless($shipping->installed(), 503);
        $payment = DB::table('logistics_shipping_payments')->where('id', $id)->firstOrFail();
        $ob = DB::table('logistics_shipping_obligations')->where('id', $payment->obligation_id)->firstOrFail();
        $shipping->participant($request->user(), $ob);
        abort_unless(preg_match('#^logistics-shipping-proofs/[A-Za-z0-9]+\.(jpg|jpeg|png|webp|pdf)$#D', $payment->proof_path)
            && Storage::disk('local')->exists($payment->proof_path), 404);
        return response()->file(Storage::disk('local')->path($payment->proof_path), ['Content-Type' => $payment->proof_mime,
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }
}
