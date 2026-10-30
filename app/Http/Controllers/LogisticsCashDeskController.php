<?php

namespace App\Http\Controllers;

use App\Services\LogisticsCashDesk;
use App\Services\LogisticsFinance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsCashDeskController extends LogisticsController
{
    public function index(Request $request, LogisticsCashDesk $desk)
    {
        $actor = $desk->actor($request->user());
        $rider = $actor->account_type === 'rider';
        $filters=$request->validate(['queue'=>'nullable|in:counts,treasury,discrepancies','handover'=>'nullable|uuid']);
        $handoverScope=$desk->scope(DB::table('logistics_cash_handovers'),$actor);
        $workCounts=['counts'=>(clone $handoverScope)->where('kind','remittance')->where('status','submitted')->count(),
            'treasury'=>(clone $handoverScope)->where('kind','treasury')->where('status','authorized')->count(),
            'discrepancies'=>(clone $handoverScope)->where('status','discrepancy')->whereNull('resolved_at')->count()];
        $handovers=(clone $handoverScope)->when($filters['handover']??null,fn($q,$id)=>$q->where('id',$id))
            ->when(($filters['queue']??null)==='counts',fn($q)=>$q->where('kind','remittance')->where('status','submitted'))
            ->when(($filters['queue']??null)==='treasury',fn($q)=>$q->where('kind','treasury')->where('status','authorized'))
            ->when(($filters['queue']??null)==='discrepancies',fn($q)=>$q->where('status','discrepancy')->whereNull('resolved_at'))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString();
        $collections = DB::table('logistics_financial_transactions')->where('company_name', $actor->business_name)->where('kind', 'cod_collection')
            ->when($rider, fn ($q) => $q->where('actor_id', $actor->id))
            ->when($actor->isHubStaff(), fn ($q) => $q->where('hub_id', $actor->logistics_hub_id))
            ->orderBy('recorded_at')->get()->map(function ($row) use ($desk) {
                $row->balance = $desk->balances($row);
                $row->due = $desk->dueAt($row);
                $row->overdue = $row->balance['outstanding'] > 0 && $row->due->isPast();
                return $row;
            })->filter(fn ($row) => $row->balance['outstanding'] > 0);
        $allocations = DB::table('logistics_cash_allocations')->whereIn('handover_id', $handovers->pluck('id'))->get()->groupBy('handover_id');
        $events = DB::table('logistics_financial_events')->where('company_name', $actor->business_name)->where('subject_type', 'cash_handover')
            ->whereIn('subject_id', $handovers->pluck('id'))->orderBy('id')->get()->groupBy('subject_id');
        $hubs = DB::table('logistics_hubs')->where('company_name', $actor->business_name)
            ->when($actor->isHubStaff(), fn ($q) => $q->where('id', $actor->logistics_hub_id))->get();
        $custody = $hubs->mapWithKeys(fn ($hub) => [$hub->id => $desk->custody($hub->id)]);
        // Transfers in transit are not counted in either hub's on-hand cash.
        $transit = $desk->scope(DB::table('logistics_cash_handovers'), $actor)->whereIn('kind', ['transfer','treasury'])->where('status', 'in_transit')->sum('expected_cents');
        $settings = $actor->isLogisticsAdmin() ? app(LogisticsFinance::class)->settings($actor->business_name) : null;
        $people = DB::table('users')->where('business_name', $actor->business_name)->get(['id','given_names','last_name','username'])->mapWithKeys(function($person){
            $name=trim(($person->given_names ?? '').' '.($person->last_name ?? ''));
            return [$person->id=>$name!=='' ? $name.' (@'.$person->username.')' : $person->username];
        });
        $recoveryRiders=DB::table('users')->where('business_name',$actor->business_name)->where('account_type','rider')->where('status','suspended')
            ->whereIn('id',$collections->pluck('actor_id'))->pluck('username','id');
        $treasuryAccounts = app(\App\Services\LogisticsTreasury::class)->installed()
            ? DB::table('logistics_treasury_accounts')->where('company_name',$actor->business_name)->whereIn('id',$handovers->pluck('treasury_account_id')->filter())->get()->keyBy('id') : collect();
        $data = compact('actor', 'rider', 'handovers', 'collections', 'allocations', 'events', 'hubs', 'custody', 'transit', 'settings', 'people', 'treasuryAccounts','recoveryRiders','workCounts');
        return response()->view('logistics.cash-desk', array_merge($rider ? [] : $this->sidebarCounts(), $data))
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function submit(Request $request, LogisticsCashDesk $desk)
    {
        $this->cashAction(fn () => $desk->submit($request->user(), $request->all()));
        return back()->with('success', 'Remittance submitted. Cash remains unverified until Hub Staff counts it.');
    }

    public function count(Request $request, string $id, LogisticsCashDesk $desk)
    {
        $this->cashAction(fn () => $desk->count($request->user(), $id, $request->all()));
        return back()->with('success', 'Cash receipt saved. Handover History records the Rider, actual count, receiving Staff, receipt time and any difference.');
    }

    public function recoverySubmission(Request $request,LogisticsCashDesk $desk)
    {
        $this->cashAction(fn()=>$desk->submitRecovery($request->user(),$request->all()));
        return back()->with('success','Recovery submission prepared for the original rider. Physical counting remains separate.');
    }

    public function withdraw(Request $request, string $id, LogisticsCashDesk $desk)
    {
        $this->cashAction(fn () => $desk->withdraw($request->user(), $id));
        return back()->with('success', 'Uncounted remittance withdrawn. Collection balances remain outstanding.');
    }

    public function authorize(Request $request, LogisticsCashDesk $desk)
    {
        $this->cashAction(fn () => $desk->authorizeTransfer($request->user(), $request->all()));
        return back()->with('success', 'Cash transfer authorized. Origin Hub Staff must confirm release.');
    }

    public function transfer(Request $request, string $id, string $action, LogisticsCashDesk $desk)
    {
        $this->cashAction(fn () => $desk->transfer($request->user(), $id, $action, $request->all()));
        return back()->with('success', $action === 'dispatch'
            ? 'Physical cash release recorded. Cash is in transit until destination Hub Staff counts receipt.'
            : 'Destination cash count recorded. Any difference remains visible for review.');
    }

    public function resolve(Request $request, string $id, LogisticsCashDesk $desk)
    {
        $data = $request->validate(['notes' => 'required|string|min:3|max:2000']);
        $this->cashAction(fn () => $desk->resolve($request->user(), $id, $data['notes']));
        return back()->with('success', 'Resolution documented. Outstanding balances and counted cash were preserved.');
    }

    public function cutoff(Request $request, LogisticsCashDesk $desk)
    {
        $this->cashAction(fn () => $desk->cutoff($request->user(), $request->all()));
        return back()->with('success', 'Company remittance cutoff updated.');
    }

    private function cashAction(callable $operation): void
    {
        try {
            $operation();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            if ($e->getStatusCode() !== 422) throw $e;
            // Show correctable cash-entry errors on the form, while preserving 403/404/409 protections.
            throw \Illuminate\Validation\ValidationException::withMessages(['cash' => $e->getMessage()]);
        }
    }
}
