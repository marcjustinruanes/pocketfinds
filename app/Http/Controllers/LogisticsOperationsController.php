<?php

namespace App\Http\Controllers;

use App\Models\{DeliveryAssignment, Shipment, User};
use App\Services\{AssignmentReassignment, AssignmentWork, LogisticsAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsOperationsController extends LogisticsController
{
    public function index(Request $request, AssignmentReassignment $workflow, AssignmentWork $work)
    {
        abort_unless($workflow->installed(),503,'Install the operational recovery migration.');
        $actor=$request->user()->fresh();$seller=$actor->account_type==='seller' && ! $actor->is_admin && ! $actor->is_logistics;
        abort_unless($actor->status==='approved',403);
        if($seller) $source=Shipment::whereHas('order',fn($q)=>$q->where('seller_id',$actor->id));
        else { $actor=$workflow->actor($actor);$source=app(LogisticsAccess::class)->shipments($actor); }
        $assignments=DeliveryAssignment::with('shipment.order')->whereIn('shipment_id',$source->select('shipments.id'))
            ->when($seller,fn($q)=>$q->where('leg','pickup')->whereNotNull('reassignment'))->orderByDesc('accepted_at')->orderBy('id')->paginate(15);
        foreach($assignments as $a)$a->work_class=$work->classify($a);
        $riders=$seller?collect():User::with('logisticsHub')->where('business_name',$actor->business_name)->where('account_type','rider')->where('status','approved')->where('is_admin',false)->where('is_logistics',false)
            ->where('vehicle_type','two_wheels')->when($actor->isHubStaff(),fn($q)=>$q->where('logistics_hub_id',$actor->logistics_hub_id))->get();
        $events=DB::table('logistics_financial_events')->where('subject_type','assignment')->whereIn('subject_id',$assignments->pluck('id'))->orderByDesc('id')->get()->groupBy('subject_id');
        return response()->view('logistics.assignment-review',array_merge($seller?[]:$this->sidebarCounts(),compact('actor','seller','assignments','riders','events')))->header('Cache-Control','private, no-store');
    }

    public function requestReassignment(Request $request,string $id,AssignmentReassignment $workflow)
    {
        $workflow->request($request->user(),$id,$request->all());
        return back()->with('success','Possession review requested. Ownership has not changed.');
    }

    public function decideReassignment(Request $request,string $id,AssignmentReassignment $workflow)
    {
        $workflow->decide($request->user(),$id,$request->all());
        return back()->with('success','Reassignment decision recorded.');
    }

    public function receiveRecovery(Request $request,\App\Services\LogisticsRecovery $workflow)
    {
        $workflow->receive($request->user(),$request->all(),$request->file('proof'));
        return back()->with('success','Recovered custody recorded. Delivery, assignments and financial balances were preserved. Redispatch remains held.');
    }
}
