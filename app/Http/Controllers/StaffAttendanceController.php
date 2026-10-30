<?php
namespace App\Http\Controllers;

use App\Services\StaffAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffAttendanceController extends LogisticsController
{
    public function index(Request $r,StaffAttendance $flow) { return $this->page($r,$flow,true); }
    public function mine(Request $r,StaffAttendance $flow) { return $this->page($r,$flow,false); }
    private function page(Request $r,StaffAttendance $flow,bool $admin)
    {
        $actor=$flow->actor($r->user(),$admin);$filters=$flow->filters($r->query());
        $records=$flow->query($actor,$filters,$admin)->orderByDesc('a.business_date')->orderBy('u.id')->orderBy('a.id')->paginate(25)->withQueryString();
        $hubs=DB::table('logistics_hubs')->where('company_name',$actor->business_name)->orderBy('municipality')->get();
        $hub=$hubs->firstWhere('id',$actor->logistics_hub_id);
        $active=$admin?null:DB::table('logistics_staff_attendance')->where('company_name',$actor->business_name)->where('active_staff_id',$actor->id)->first();
        $today=$admin?collect():DB::table('logistics_staff_attendance')->where('company_name',$actor->business_name)->where('staff_id',$actor->id)->where('business_date',$flow->now()->toDateString())->where('kind','!=','void')->get();
        $corrections=DB::table('logistics_staff_attendance_corrections as c')->join('users as u','u.id','=','c.staff_id')
            ->leftJoin('logistics_staff_attendance as a','a.id','=','c.attendance_id')->where('c.company_name',$actor->business_name)
            ->when(!$admin,fn($q)=>$q->where('c.staff_id',$actor->id))->when($filters['corrections'],fn($q)=>$q->where('c.status',$filters['corrections']))
            ->when($filters['hub'],fn($q)=>$q->where('c.hub_id',$filters['hub']))->when($filters['staff_id'],fn($q)=>$q->where('c.staff_id',$filters['staff_id']))
            ->when($filters['q']!=='',function($q)use($filters){$value='%'.str_replace(['!','%','_'],['!!','!%','!_'],$filters['q']).'%';$q->where(function($q)use($value,$filters){$q->whereRaw("u.given_names LIKE ? ESCAPE '!'",[$value])->orWhereRaw("u.last_name LIKE ? ESCAPE '!'",[$value])->orWhereRaw((DB::getDriverName()==='sqlite'?"(u.given_names || ' ' || u.last_name)":"CONCAT(u.given_names, ' ', u.last_name)")." LIKE ? ESCAPE '!'",[$value]);if(ctype_digit($filters['q']))$q->orWhere('u.id',(int)$filters['q']);});})
            ->select('c.*','u.given_names','u.last_name','a.time_in as current_in','a.time_out as current_out','a.original_in','a.original_out')->orderByDesc('c.created_at')->orderBy('c.id')->paginate(20,['*'],'corrections_page')->withQueryString();
        return response()->view('logistics.staff-attendance',array_merge($this->sidebarCounts(),compact('admin','actor','filters','records','hubs','hub','active','today','corrections','flow')))->header('Cache-Control','private, no-store');
    }
    private function run(callable $fn)
    {
        try { $fn(); }
        catch (\Illuminate\Database\QueryException $e) {
            $code=$e->errorInfo[1]??null;
            if($code===1062 || ($code===19 && str_contains(strtolower($e->getMessage()),'unique constraint failed')))
                throw \Illuminate\Validation\ValidationException::withMessages(['attendance'=>'This attendance action already exists. Refresh before retrying.']);
            throw $e;
        }
        return back()->with('success','Attendance action recorded. Salary amounts and payment eligibility were not changed.');
    }
    public function clock(Request $r,string $action,StaffAttendance $flow){return $this->run(fn()=>$flow->clock($r->user(),$action));}
    public function requestCorrection(Request $r,StaffAttendance $flow){return $this->run(fn()=>$flow->requestCorrection($r->user(),$r->all()));}
    public function decide(Request $r,string $id,StaffAttendance $flow){return $this->run(fn()=>$flow->decide($r->user(),$id,$r->all()));}
    public function mark(Request $r,StaffAttendance $flow){return $this->run(fn()=>$flow->mark($r->user(),$r->all()));}
    public function review(Request $r,string $id,StaffAttendance $flow){return $this->run(fn()=>$flow->review($r->user(),$id,$r->all()));}
    public function show(Request $r,string $id,StaffAttendance $flow)
    {
        $admin=$r->routeIs('logistics.attendance.*');$actor=$flow->actor($r->user(),$admin);
        $record=DB::table('logistics_staff_attendance')->where('id',$id)->where('company_name',$actor->business_name)->when(!$admin,fn($q)=>$q->where('staff_id',$actor->id))->firstOrFail();
        $member=DB::table('users')->where('id',$record->staff_id)->where('business_name',$actor->business_name)->first(['given_names','last_name']);
        $hub=DB::table('logistics_hubs')->where('id',$record->hub_id)->where('company_name',$actor->business_name)->first(['municipality','province']);
        $events=DB::table('logistics_staff_attendance_events as e')
            ->leftJoin('users as u',fn($j)=>$j->on('u.id','=','e.actor_id')->where('u.business_name',$actor->business_name))
            ->where('e.company_name',$actor->business_name)->where('e.subject_id',$id)
            ->select('e.*','u.given_names as actor_given_names','u.last_name as actor_last_name')->orderByDesc('e.id')->paginate(20);
        return response()->view('logistics.staff-attendance-detail',array_merge($this->sidebarCounts(),compact('record','events','admin','flow','member','hub')))->header('Cache-Control','private, no-store');
    }
    public function export(Request $r,StaffAttendance $flow)
    {
        $actor=$flow->actor($r->user(),true);$filters=$flow->filters($r->query());
        $rows=$flow->query($actor,$filters,true)->orderBy('u.id')->orderBy('a.business_date')->orderBy('a.id')->limit(10001)->get();
        abort_if($rows->count()>10000,422,'Narrow export to at most 10,000 records.');
        DB::transaction(function()use($actor,$filters,$rows){$fresh=app(StaffAttendance::class)->actor($actor,true);abort_unless($fresh->business_name===$actor->business_name,403);
            DB::table('logistics_staff_attendance_events')->insert(['company_name'=>$actor->business_name,'staff_id'=>$actor->id,'subject_id'=>(string)\Illuminate\Support\Str::uuid(),'event'=>'attendance_exported','actor_id'=>$actor->id,'details'=>json_encode(['filters'=>$filters,'rows'=>$rows->count()]),'created_at'=>now()]);});
        return response()->streamDownload(function()use($rows,$flow){$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['staff_id','name','hub','business_date_pht','status','time_in_pht','time_out_pht','recorded_minutes_not_payroll','corrected'],',','"','');
            foreach($rows as $row){$fmt=fn($v)=>$v?\Carbon\CarbonImmutable::parse($v,config('app.timezone'))->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'):'';
                $minutes=$row->time_in && $row->time_out?(int)\Carbon\CarbonImmutable::parse($row->time_in)->diffInMinutes(\Carbon\CarbonImmutable::parse($row->time_out)):'';
                fputcsv($out,array_map([\App\Services\LogisticsReconciliation::class,'csvCell'],[$row->member_id,$row->given_names.' '.$row->last_name,$row->hub_name,$row->business_date,$flow->status($row),$fmt($row->time_in),$fmt($row->time_out),$minutes,$row->corrected]),',','"','');}fclose($out);
        },'staff-attendance.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
