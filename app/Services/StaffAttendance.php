<?php
namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffAttendance
{
    public function installed(): bool { return Schema::hasTable('logistics_staff_attendance_events'); }
    public function now(): CarbonImmutable { return CarbonImmutable::now('Asia/Manila'); }
    public function actor(User $actor,bool $admin=false): User
    {
        abort_unless($this->installed(),503,'Hub salary and attendance migration required.');
        $actor=User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        if($admin) return app(StaffSalary::class)->admin($actor);
        $actor=app(StaffSalary::class)->staff($actor);
        app(LogisticsAccess::class)->hub($actor);
        return $actor;
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['attendance'=>$message]); }
    private function event(User $actor,int $staff,string $subject,string $event,array $details=[]): void
    {
        DB::table('logistics_staff_attendance_events')->insert(['company_name'=>$actor->business_name,'staff_id'=>$staff,'subject_id'=>$subject,'event'=>$event,'actor_id'=>$actor->id,'details'=>json_encode($details),'created_at'=>now()]);
    }
    private function stamp(CarbonImmutable $time): string { return $time->setTimezone(config('app.timezone'))->toDateTimeString(); }
    private function shiftStatus(object $row): string
    {
        if($row->kind!=='shift') return $row->kind;
        return $row->time_out?'completed':($row->needs_review?'missing_timeout':'working');
    }
    public function status(object $row): string { return isset($row->id)?$this->shiftStatus($row):'no_record'; }
    public function label(object $row): string { return ['working'=>'Working','completed'=>'Completed','missing_timeout'=>'Missing Time Out','leave'=>'On Leave','absence'=>'Confirmed absence','no_record'=>'No Record','void'=>'Voided day mark'][$this->status($row)]; }

    public function clock(User $actor,string $action): void
    {
        abort_unless(in_array($action,['in','out'],true),404);
        DB::transaction(function()use($actor,$action){
            $actor=$this->actor($actor);$time=$this->now();
            $active=DB::table('logistics_staff_attendance')->where('active_staff_id',$actor->id)->lockForUpdate()->first();
            if($action==='in'){
                if($active) $this->fail('You already have an active shift. Time Out or request a correction.');
                $blocked=DB::table('logistics_staff_attendance')->where('staff_id',$actor->id)->where('business_date',$time->toDateString())->whereIn('kind',['leave','absence'])->lockForUpdate()->first();
                if($blocked) $this->fail('A documented leave or absence exists today. Ask Admin to review it before timing in.');
                $id=(string)Str::uuid();
                DB::table('logistics_staff_attendance')->insert(['id'=>$id,'company_name'=>$actor->business_name,'staff_id'=>$actor->id,'hub_id'=>$actor->logistics_hub_id,'business_date'=>$time->toDateString(),'kind'=>'shift','time_in'=>$this->stamp($time),'original_in'=>$this->stamp($time),'active_staff_id'=>$actor->id,'created_at'=>now(),'updated_at'=>now()]);
                $this->event($actor,$actor->id,$id,'time_in',['server_time'=>$this->stamp($time),'hub_id'=>$actor->logistics_hub_id]);
            }else{
                if(!$active || $active->company_name!==$actor->business_name) $this->fail('No active shift is available for Time Out.');
                abort_unless($time->greaterThanOrEqualTo(CarbonImmutable::parse($active->time_in,config('app.timezone'))),409,'Server clock precedes Time In.');
                DB::table('logistics_staff_attendance')->where('id',$active->id)->update(['time_out'=>$this->stamp($time),'original_out'=>$active->original_out??$this->stamp($time),'active_staff_id'=>null,'needs_review'=>false,'revision'=>$active->revision+1,'updated_at'=>now()]);
                $this->event($actor,$actor->id,$active->id,'time_out',['server_time'=>$this->stamp($time),'shift_business_date'=>$active->business_date]);
            }
        },3);
    }

    public function requestCorrection(User $actor,array $input): void
    {
        $d=Validator::make($input,['attendance_id'=>'nullable|uuid','business_date'=>'required|date_format:Y-m-d','requested_in'=>'required|date_format:Y-m-d\TH:i','requested_out'=>'nullable|date_format:Y-m-d\TH:i','reason'=>'required|string|min:5|max:2000'])->validate();
        $in=CarbonImmutable::createFromFormat('Y-m-d\TH:i',$d['requested_in'],'Asia/Manila')->startOfMinute();
        $out=filled($d['requested_out']??null)?CarbonImmutable::createFromFormat('Y-m-d\TH:i',$d['requested_out'],'Asia/Manila')->startOfMinute():null;
        if($in->toDateString()!==$d['business_date'] || $in->isFuture() || ($out && ($out->lessThan($in) || $out->isFuture()))) $this->fail('Requested times must be valid past PHT times. The affected date is the Time In date; Time Out may be on a later date.');
        DB::transaction(function()use($actor,$d,$in,$out){
            $actor=$this->actor($actor);$row=null;
            if(!empty($d['attendance_id'])){
                $row=DB::table('logistics_staff_attendance')->where('id',$d['attendance_id'])->where('staff_id',$actor->id)->where('company_name',$actor->business_name)->lockForUpdate()->firstOrFail();
                abort_unless($row->kind==='shift' && $row->business_date===$d['business_date'],422,'Request must match this shift date.');
            }elseif(DB::table('logistics_staff_attendance')->where('staff_id',$actor->id)->where('business_date',$d['business_date'])->where('kind','!=','void')->lockForUpdate()->first()) $this->fail('An attendance record exists for that date. Select its shift ID or ask Admin to review the documented leave/absence.');
            $key=$actor->id.':'.($row?->id??$d['business_date']);
            if(DB::table('logistics_staff_attendance_corrections')->where('pending_key',$key)->lockForUpdate()->first()) $this->fail('A pending correction already exists for this issue.');
            $id=(string)Str::uuid();
            DB::table('logistics_staff_attendance_corrections')->insert(['id'=>$id,'company_name'=>$actor->business_name,'staff_id'=>$actor->id,'hub_id'=>$row?->hub_id??$actor->logistics_hub_id,'attendance_id'=>$row?->id,'business_date'=>$d['business_date'],'base_revision'=>$row?->revision,'requested_in'=>$this->stamp($in),'requested_out'=>$out?$this->stamp($out):null,'reason'=>$d['reason'],'pending_key'=>$key,'created_at'=>now(),'updated_at'=>now()]);
            $this->event($actor,$actor->id,$id,'correction_requested',['attendance_id'=>$row?->id,'requested_in'=>$this->stamp($in),'requested_out'=>$out?$this->stamp($out):null,'reason'=>$d['reason']]);
        },3);
    }

    private function overlaps(int $staff,string $in,?string $out,?string $except=null): bool
    {
        return DB::table('logistics_staff_attendance')->where('staff_id',$staff)->where('kind','shift')->when($except,fn($q)=>$q->where('id','!=',$except))
            ->where(fn($q)=>$q->whereNull('time_out')->orWhere('time_out','>',$in))->when($out,fn($q)=>$q->where('time_in','<',$out))->lockForUpdate()->first() !== null;
    }

    public function decide(User $actor,string $id,array $input): void
    {
        $d=Validator::make($input,['decision'=>'required|in:approve,reject','reason'=>'required|string|min:5|max:2000'])->validate();
        DB::transaction(function()use($actor,$id,$d){
            $actor=$this->actor($actor,true);
            $request=DB::table('logistics_staff_attendance_corrections')->where('id',$id)->where('company_name',$actor->business_name)->lockForUpdate()->firstOrFail();
            abort_unless($request->status==='pending',409,'This request has already been decided.');
            abort_unless($request->staff_id!=$actor->id,403,'You cannot decide your own correction.');
            $member=User::whereKey($request->staff_id)->lockForUpdate()->firstOrFail();
            abort_unless($member->business_name===$actor->business_name && $member->isHubStaff(),403);
            $row=$request->attendance_id?DB::table('logistics_staff_attendance')->where('id',$request->attendance_id)->where('company_name',$actor->business_name)->lockForUpdate()->firstOrFail():null;
            if($d['decision']==='approve'){
                if($row && (int)$row->revision!==(int)$request->base_revision) $this->fail('The shift changed since this request. Reject with a reason and ask Staff to submit a fresh correction.');
                if(!$row && DB::table('logistics_staff_attendance')->where('staff_id',$member->id)->where('business_date',$request->business_date)->where('kind','!=','void')->lockForUpdate()->first()) $this->fail('An attendance record now exists for this date. Review before approving.');
                if($this->overlaps($member->id,$request->requested_in,$request->requested_out,$row?->id)) $this->fail('Requested times overlap another shift or an active shift.');
                $leave=DB::table('logistics_staff_attendance')->where('staff_id',$member->id)->whereBetween('business_date',[$request->business_date,CarbonImmutable::parse($request->requested_out??$request->requested_in,config('app.timezone'))->setTimezone('Asia/Manila')->toDateString()])->whereIn('kind',['leave','absence'])->lockForUpdate()->first();
                if($leave) $this->fail('Documented leave or absence conflicts with the correction. Review it first.');
                $values=['time_in'=>$request->requested_in,'time_out'=>$request->requested_out,'active_staff_id'=>$request->requested_out?null:$member->id,'corrected'=>true,'needs_review'=>false,'revision'=>($row?->revision??0)+1,'updated_at'=>now()];
                $attendance=$row?->id??(string)Str::uuid();
                if($row) DB::table('logistics_staff_attendance')->where('id',$attendance)->update($values);
                else DB::table('logistics_staff_attendance')->insert($values+['id'=>$attendance,'company_name'=>$actor->business_name,'staff_id'=>$member->id,'hub_id'=>$request->hub_id,'business_date'=>$request->business_date,'kind'=>'shift','created_at'=>now()]);
                $this->event($actor,$member->id,$attendance,'correction_applied',['request_id'=>$id,'original_in'=>$row?->original_in,'original_out'=>$row?->original_out,'before_in'=>$row?->time_in,'before_out'=>$row?->time_out,'corrected_in'=>$request->requested_in,'corrected_out'=>$request->requested_out,'reason'=>$d['reason']]);
            }
            DB::table('logistics_staff_attendance_corrections')->where('id',$id)->update(['status'=>$d['decision']==='approve'?'approved':'rejected','pending_key'=>null,'decided_by'=>$actor->id,'decision_reason'=>$d['reason'],'decided_at'=>now(),'updated_at'=>now()]);
            $this->event($actor,$member->id,$id,'correction_'.$d['decision'],['reason'=>$d['reason']]);
        },3);
    }

    public function mark(User $actor,array $input): void
    {
        $d=Validator::make($input,['staff_id'=>'required|integer','business_date'=>'required|date_format:Y-m-d','kind'=>'required|in:leave,absence','reason'=>'required|string|min:5|max:2000'])->validate();
        if($d['kind']==='absence') Validator::make($input,['scheduled_confirmed'=>'required|accepted'])->validate();
        DB::transaction(function()use($actor,$d){
            $actor=$this->actor($actor,true);$member=User::whereKey($d['staff_id'])->lockForUpdate()->firstOrFail();
            abort_unless($member->isHubStaff() && $member->business_name===$actor->business_name && filled($member->logistics_hub_id),403);
            if($d['kind']==='absence' && $d['business_date']>$this->now()->toDateString()) $this->fail('A future day cannot be confirmed absent.');
            $start=CarbonImmutable::parse($d['business_date'],'Asia/Manila');$end=$start->addDay();
            if(DB::table('logistics_staff_attendance')->where('staff_id',$member->id)->where('business_date',$d['business_date'])->where('kind','!=','void')->lockForUpdate()->first() || $this->overlaps($member->id,$this->stamp($start),$this->stamp($end))) $this->fail('A shift, leave or absence already overlaps this day.');
            $id=(string)Str::uuid();
            DB::table('logistics_staff_attendance')->insert(['id'=>$id,'company_name'=>$actor->business_name,'staff_id'=>$member->id,'hub_id'=>$member->logistics_hub_id,'business_date'=>$d['business_date'],'kind'=>$d['kind'],'day_key'=>$member->id.':'.$d['business_date'],'reason'=>$d['reason'],'created_at'=>now(),'updated_at'=>now()]);
            $this->event($actor,$member->id,$id,'day_'.$d['kind'],['date'=>$d['business_date'],'reason'=>$d['reason'],'scheduled_confirmed'=>$d['kind']==='absence']);
        },3);
    }

    public function review(User $actor,string $id,array $input): void
    {
        $d=Validator::make($input,['revision'=>'required|integer|min:1','action'=>'required|in:missing_timeout,void','reason'=>'required|string|min:5|max:2000'])->validate();
        DB::transaction(function()use($actor,$id,$d){
            $actor=$this->actor($actor,true);
            $peek=DB::table('logistics_staff_attendance')->where('id',$id)->where('company_name',$actor->business_name)->firstOrFail();
            User::whereKey($peek->staff_id)->lockForUpdate()->firstOrFail();
            $row=DB::table('logistics_staff_attendance')->where('id',$id)->where('company_name',$actor->business_name)->lockForUpdate()->firstOrFail();
            abort_unless((int)$row->revision===(int)$d['revision'],409,'Record changed; refresh.');
            if($d['action']==='missing_timeout'){
                abort_unless($row->kind==='shift' && !$row->time_out,422,'Only an open shift can be flagged.');$v=['needs_review'=>true];
            }else{
                abort_unless(in_array($row->kind,['leave','absence']),422,'Only documented day marks may be voided here.');$v=['kind'=>'void','day_key'=>null];
            }
            DB::table('logistics_staff_attendance')->where('id',$id)->update($v+['revision'=>$row->revision+1,'updated_at'=>now()]);
            $this->event($actor,$row->staff_id,$id,$d['action'],['before'=>(array)$row,'reason'=>$d['reason']]);
        },3);
    }

    public function filters(array $input): array
    {
        $d=Validator::make($input,['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','q'=>'nullable|string|max:100','hub'=>'nullable|integer|min:1','staff_id'=>'nullable|integer|min:1','status'=>'nullable|in:working,completed,missing_timeout,leave,absence,no_record,void','corrections'=>'nullable|in:pending,approved,rejected'])->validate();
        $d+=['from'=>$this->now()->startOfMonth()->toDateString(),'to'=>$this->now()->toDateString(),'q'=>'','hub'=>null,'staff_id'=>null,'status'=>'','corrections'=>'pending'];
        foreach(['q'=>'','hub'=>null,'staff_id'=>null,'status'=>'','corrections'=>'pending'] as $k=>$v) if(!isset($d[$k]))$d[$k]=$v;
        $d['from']=$d['from']??$this->now()->startOfMonth()->toDateString();$d['to']=$d['to']??$this->now()->toDateString();
        abort_unless($d['from']<=$d['to'] && CarbonImmutable::parse($d['from'])->diffInDays(CarbonImmutable::parse($d['to']))<=93,422,'Choose an ordered date range of at most 94 calendar days.');return $d;
    }

    public function query(User $actor,array $f,bool $admin)
    {
        $q=DB::table('users as u')->where('u.business_name',$actor->business_name)->where('u.account_type','logistics')->where('u.is_logistics',true)->where('u.logistics_role','hub_staff')
            ->when(!$admin,fn($q)=>$q->where('u.id',$actor->id))
            ->leftJoin('logistics_staff_attendance as a',fn($j)=>$j->on('a.staff_id','=','u.id')->where('a.company_name',$actor->business_name)->whereBetween('a.business_date',[$f['from'],$f['to']])->when($f['status']!=='void',fn($j)=>$j->where('a.kind','!=','void')))
            ->leftJoin('logistics_hubs as h','h.id','=',DB::raw('COALESCE(a.hub_id,u.logistics_hub_id)'))
            ->select('a.*','u.id as member_id','u.given_names','u.last_name','h.municipality as hub_name','u.logistics_hub_id as assigned_hub_id','u.status as staff_status');
        if($f['hub'])$q->whereRaw('COALESCE(a.hub_id,u.logistics_hub_id) = ?',[$f['hub']]);
        if($f['staff_id'])$q->where('u.id',$f['staff_id']);
        if($f['q']!=='')$q->where(function($q)use($f){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$f['q']).'%';$q->whereRaw("u.given_names LIKE ? ESCAPE '!'",[$term])->orWhereRaw("u.last_name LIKE ? ESCAPE '!'",[$term])->orWhereRaw((DB::getDriverName()==='sqlite'?"(u.given_names || ' ' || u.last_name)":"CONCAT(u.given_names, ' ', u.last_name)")." LIKE ? ESCAPE '!'",[$term]);if(ctype_digit($f['q']))$q->orWhere('u.id',(int)$f['q']);});
        switch($f['status']){
            case 'working':$q->where('a.kind','shift')->whereNull('a.time_out')->where('a.needs_review',false);break;
            case 'completed':$q->where('a.kind','shift')->whereNotNull('a.time_out');break;
            case 'missing_timeout':$q->where('a.kind','shift')->whereNull('a.time_out')->where('a.needs_review',true);break;
            case 'no_record':$q->whereNull('a.id');break;
            case 'leave':case 'absence':case 'void':$q->where('a.kind',$f['status']);break;
        }
        return $q;
    }

    public function summary(string $company,array $staffIds,string $from,string $to): array
    {
        $to=min($to,$this->now()->toDateString());
        if(!$this->installed() || !$staffIds || $from>$to) return [];
        $rows=DB::table('logistics_staff_attendance')->where('company_name',$company)->whereIn('staff_id',$staffIds)->whereBetween('business_date',[$from,$to])->selectRaw("staff_id, COUNT(DISTINCT CASE WHEN kind='shift' THEN business_date END) workdays, COUNT(DISTINCT CASE WHEN kind='leave' THEN business_date END) leave_days, COUNT(DISTINCT CASE WHEN kind='absence' THEN business_date END) absence_days, COUNT(DISTINCT CASE WHEN kind!='void' THEN business_date END) recorded_days, SUM(CASE WHEN kind='shift' AND time_out IS NULL THEN 1 ELSE 0 END) open_shifts, SUM(CASE WHEN needs_review=1 AND time_out IS NULL THEN 1 ELSE 0 END) missing_timeout")->groupBy('staff_id')->get()->keyBy('staff_id');
        $pending=DB::table('logistics_staff_attendance_corrections')->where('company_name',$company)->whereIn('staff_id',$staffIds)->whereBetween('business_date',[$from,$to])->where('status','pending')->selectRaw('staff_id, COUNT(*) total')->groupBy('staff_id')->pluck('total','staff_id');
        $days=(int)CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to))+1;$out=[];
        foreach($staffIds as $id){$r=$rows[$id]??null;$out[$id]=['workdays'=>(int)($r?->workdays??0),'leave'=>(int)($r?->leave_days??0),'absence'=>(int)($r?->absence_days??0),'no_record'=>max(0,$days-(int)($r?->recorded_days??0)),'open'=>(int)($r?->open_shifts??0),'missing_timeout'=>(int)($r?->missing_timeout??0),'pending'=>(int)($pending[$id]??0)];}return $out;
    }
}
