<?php

namespace App\Http\Controllers;

use App\Services\LogisticsFinance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsFinanceController extends LogisticsController
{
    public function index(Request $request, LogisticsFinance $finance)
    {
        $actor = $finance->admin($request->user());
        abort_unless($finance->installed(), 503, 'The financial foundation migration has not been installed.');
        $settings = $finance->settings($actor->business_name);
        $snapshots = DB::table('logistics_compensations as c')->join('users as u', 'u.id', '=', 'c.rider_id')
            ->where('c.company_name', $actor->business_name)->orderByDesc('c.created_at')->orderByDesc('c.id')
            ->select('c.*', 'u.username')->paginate(20);
        $collections = app(\App\Services\CodCollection::class)->installed()
            ? DB::table('logistics_financial_transactions as t')->join('users as r', 'r.id', '=', 't.actor_id')
                ->leftJoin('logistics_hubs as h', 'h.id', '=', 't.hub_id')->where('t.company_name', $actor->business_name)
                ->where('t.kind', 'cod_collection')->orderByDesc('t.recorded_at')->orderByDesc('t.id')
                ->select('t.*', 'r.username', 'h.municipality as hub_municipality')->paginate(20, ['*'], 'collection_page')
            : null;

        $shipping = null;
        if (app(\App\Services\LogisticsShipping::class)->installed()) {
            $shipping = [
                'account' => DB::table('logistics_shipping_accounts')->where('company_name', $actor->business_name)->first(),
                'rates' => DB::table('logistics_shipping_rates')->where('company_name', $actor->business_name)->where('is_active', true)->orderBy('region')->get(),
                'obligations' => DB::table('logistics_shipping_obligations')->where('company_name', $actor->business_name)->orderByDesc('created_at')->paginate(15, ['*'], 'shipping_page'),
            ];
        }
        return view('logistics.finance', array_merge($this->sidebarCounts(), compact('settings', 'snapshots', 'collections', 'shipping')));
    }

    public function settings(Request $request, LogisticsFinance $finance)
    {
        $finance->admin($request->user());
        abort_unless($finance->installed(), 503);
        $data = $request->validate([
            'revision' => 'required|integer|min:1',
            'pickup' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,6})(?:\.[0-9]{1,2})?$/D'],
            'transfer' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,6})(?:\.[0-9]{1,2})?$/D'],
            'delivery' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,6})(?:\.[0-9]{1,2})?$/D'],
        ]);
        $finance->configure($request->user(), $data);

        return redirect()->route('logistics.finance')->with('success', 'Compensation rates saved. Existing assignment snapshots were preserved.');
    }
}
