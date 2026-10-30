<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Company-scoped read model and bounded orchestration of the existing StaffSalary transactions. */
class StaffPayrollWorkspace
{
    public const LIMIT = 25;
    public const STATUSES = ['not_generated','pending','prepared','verified','paid','acknowledged'];
    public const BLOCKERS = ['missing_salary','missing_gcash','suspended','overdue','insufficient_funding','not_eligible','held'];

    public function filters(array $input): array
    {
        $data=Validator::make($input,[
            'q'=>'nullable|string|max:100','hub'=>'nullable|integer|min:1',
            'period_year'=>'nullable|integer|min:2020|max:2100','period_month'=>'nullable|integer|min:1|max:12','period_half'=>'nullable|integer|in:1,2',
            'status'=>['nullable',Rule::in(self::STATUSES)],'blocker'=>['nullable',Rule::in(self::BLOCKERS)],
            'page'=>'nullable|integer|min:1|max:100000','payouts_page'=>'nullable|integer|min:1|max:100000','funding_page'=>'nullable|integer|min:1|max:100000',
        ])->validate();
        [$year,$month,$half]=app(StaffSalary::class)->currentPeriod();
        foreach(['period_year'=>$year,'period_month'=>$month,'period_half'=>$half,'page'=>1,'q'=>'','hub'=>null,'status'=>'','blocker'=>''] as $key=>$value) if(!isset($data[$key])) $data[$key]=$value;
        return $data;
    }

    public function accounts(string $company)
    {
        return DB::table('logistics_treasury_accounts')->where('company_name',$company)->where('channel','gcash')->orderBy('id')->get();
    }

    public function funds(string $company): array
    {
        $ids=$this->accounts($company)->pluck('id');
        $balances=DB::table('logistics_treasury_movements')->whereIn('account_id',$ids)->where('bucket','staff_payroll')->selectRaw('account_id, SUM(delta_cents) total')->groupBy('account_id')->pluck('total','account_id');
        $reserved=DB::table('logistics_staff_payouts')->where('company_name',$company)->whereIn('status',['prepared','verified'])->selectRaw('treasury_account_id, SUM(amount_cents) total')->groupBy('treasury_account_id')->pluck('total','treasury_account_id');
        return $ids->mapWithKeys(fn($id)=>[$id=>['balance'=>(int)($balances[$id]??0),'available'=>(int)($balances[$id]??0)-(int)($reserved[$id]??0)]])->all();
    }

    public function query(string $company,array $f)
    {
        $start=CarbonImmutable::create($f['period_year'],$f['period_month'],$f['period_half']==1?1:16,0,0,0,'Asia/Manila');
        $end=($f['period_half']==1?$start->setDay(15):$start->endOfMonth())->endOfDay()->setTimezone(config('app.timezone'))->toDateTimeString();
        $at=$start->setTimezone(config('app.timezone'))->toDateTimeString();
        $enhanced=app(HubStaffSalary::class)->installed();
        $rate=function($first=false)use($company,$at,$start,$enhanced){
            $q=DB::table('logistics_staff_salaries as s')->select('s.id')->whereColumn('s.staff_id','u.id')->where('s.company_name',$company);
            if($enhanced){
                if($first) $q->where(fn($q)=>$q->whereNull('s.period_effective')->orWhere('s.period_effective','<=',$start->toDateString()));
                else $q->where(fn($q)=>$q->where('s.period_effective','<=',$start->toDateString())->orWhere(fn($q)=>$q->whereNull('s.period_effective')->where('s.effective_from','<=',$at)));
            }elseif(!$first) $q->where('s.effective_from','<=',$at);
            return $q->orderBy('s.revision',$first?'asc':'desc')->limit(1);
        };
        $basis=DB::query()->selectRaw('COALESCE(('.$rate()->toSql().'),('.$rate(true)->toSql().'))',array_merge($rate()->getBindings(),$rate(true)->getBindings()));
        $latest=DB::table('logistics_staff_salaries as s')->select('s.id')->whereColumn('s.staff_id','u.id')->where('s.company_name',$company)->orderByDesc('s.revision')->limit(1);
        $dest=DB::table('logistics_staff_destinations as d')->select('d.id')->whereColumn('d.staff_id','u.id')->orderByDesc('d.created_at')->orderByDesc('d.id')->limit(1);
        $q=DB::table('users as u')->where('u.business_name',$company)->where('u.account_type','logistics')->where('u.is_logistics',true)->where('u.is_admin',false)->where('u.logistics_role','hub_staff')
            ->whereIn('u.status',['approved','suspended'])
            ->leftJoin('logistics_hubs as h',fn($j)=>$j->on('h.id','=','u.logistics_hub_id')->where('h.company_name',$company))
            ->leftJoin('logistics_staff_salary_periods as p',fn($j)=>$j->on('p.staff_id','=','u.id')->where('p.company_name',$company)->where('p.period_year',$f['period_year'])->where('p.period_month',$f['period_month'])->where('p.period_half',$f['period_half']))
            ->leftJoin('logistics_staff_salaries as snap','snap.id','=','p.salary_id')
            ->leftJoin('logistics_staff_payouts as po',fn($j)=>$j->on('po.period_id','=','p.id')->where('po.company_name',$company))
            ->leftJoin('logistics_staff_salaries as rate',fn($j)=>$j->where('rate.id','=',$latest))
            ->leftJoin('logistics_staff_salaries as basis',fn($j)=>$j->where('basis.id','=',$basis))
            ->leftJoin('logistics_staff_destinations as dest',fn($j)=>$j->where('dest.id','=',DB::query()->selectRaw("CASE WHEN po.status IN ('prepared','verified','paid') THEN po.destination_id ELSE (".$dest->toSql().") END",$dest->getBindings())));
        $half=DB::getDriverName()==='sqlite'?'CAST(basis.monthly_cents / 2 AS INTEGER)':'FLOOR(basis.monthly_cents / 2)';
        if((int)$f['period_half']===2) $half.=' + (basis.monthly_cents % 2)';
        $amount='COALESCE(p.amount_cents, CASE WHEN basis.monthly_cents > 0 THEN '.$half.' ELSE NULL END)';
        $eligible=$start->greaterThan(CarbonImmutable::now('Asia/Manila'))?'1=0':'(basis.effective_from <= ? AND basis.monthly_cents > 0)';
        $eligibleBindings=$eligible==='1=0'?[]:[$end];
        $q->select('u.id','u.given_names','u.last_name','u.status as staff_status','u.logistics_hub_id','h.municipality as hub_name','h.province as hub_province',
            'rate.monthly_cents','rate.revision','basis.id as basis_id','basis.monthly_cents as basis_monthly','p.id as period_id','p.salary_id','p.status as period_status','p.pay_date',
            'po.id as payout_id','po.revision as payout_revision','po.status as payout_status','po.acknowledged_at','po.hold_reason',
            'dest.id as destination_id','dest.account_name','dest.account_number','dest.channel')
            ->selectSub(DB::table('logistics_staff_destinations as tied')->selectRaw("CASE WHEN po.status IN ('prepared','verified','paid') THEN 1 ELSE COUNT(*) END")->whereColumn('tied.staff_id','u.id')->whereColumn('tied.created_at','dest.created_at'),'destination_ties')
            ->selectRaw('COALESCE(snap.monthly_cents, NULLIF(basis.monthly_cents, 0)) as snapshot_monthly_cents')->selectRaw($amount.' as installment_cents')->selectRaw('CASE WHEN '.$eligible.' THEN 1 ELSE 0 END as can_generate',$eligibleBindings);
        if($enhanced) $q->addSelect('rate.salary_source','rate.source_hub_id','rate.period_effective');
        else $q->selectRaw("'individual' as salary_source, NULL as source_hub_id, NULL as period_effective");
        if($f['q']!=='') $q->where(function($q)use($f){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$f['q']).'%';$q->whereRaw("u.given_names LIKE ? ESCAPE '!'",[$term])->orWhereRaw("u.last_name LIKE ? ESCAPE '!'",[$term])->orWhereRaw((DB::getDriverName()==='sqlite'?"(u.given_names || ' ' || u.last_name)":"CONCAT(u.given_names, ' ', u.last_name)")." LIKE ? ESCAPE '!'",[$term]);if(ctype_digit($f['q'])) $q->orWhere('u.id',(int)$f['q']);});
        if($f['hub']) $q->where('u.logistics_hub_id',$f['hub']);
        if($f['status']==='not_generated') $q->whereNull('p.id');
        elseif($f['status']==='acknowledged') $q->whereNotNull('po.acknowledged_at');
        elseif($f['status']) $q->where('p.status',$f['status']);
        $today=CarbonImmutable::now('Asia/Manila')->toDateString();
        switch($f['blocker']){
            case 'missing_salary':$q->where(fn($q)=>$q->whereNull('rate.id')->orWhere('rate.monthly_cents','<=',0));break;
            case 'missing_gcash':$q->where(fn($q)=>$q->whereNull('dest.id')->orWhere('dest.channel','!=','gcash')->orWhereRaw("(COALESCE(po.status,'') NOT IN ('prepared','verified','paid') AND (SELECT COUNT(*) FROM logistics_staff_destinations tied WHERE tied.staff_id = u.id AND tied.created_at = dest.created_at) > 1)"));break;
            case 'suspended':$q->where('u.status','suspended');break;
            case 'overdue':$q->where('p.pay_date','<',$today)->where('p.status','!=','paid');break;
            case 'held':$q->whereNotNull('po.hold_reason');break;
            case 'not_eligible':$q->whereNull('p.id')->whereRaw('(NOT ('.$eligible.') OR basis.id IS NULL)',$eligibleBindings);break;
            case 'insufficient_funding':
                $available=max([0,...array_column($this->funds($company),'available')]);
                $q->where('p.status','pending')->whereRaw($amount.' > ?',[$available]);break;
        }
        return $q;
    }

    public function decorate(object $row,array $f,array $funds): object
    {
        $row->due_date=$row->pay_date??app(StaffSalary::class)->payDate((int)$f['period_year'],(int)$f['period_month'],(int)$f['period_half'])->toDateString();
        $row->labels=[];
        if($row->staff_status!=='approved') $row->labels[]='Suspended Staff';
        if(!$row->monthly_cents) $row->labels[]='Missing salary configuration';
        if(!$row->destination_id || $row->destination_ties>1 || $row->channel!=='gcash' || !preg_match('/^09[0-9]{9}$/D',(string)$row->account_number)) $row->labels[]=$row->destination_ties>1?'GCash account needs confirmation':'Missing or invalid GCash account';
        if(!$row->period_id && !$row->can_generate) $row->labels[]='Period not eligible';
        if($row->period_status==='pending' && (int)$row->installment_cents>max([0,...array_column($funds,'available')])) $row->labels[]='Insufficient funding';
        if($row->period_id && $row->period_status!=='paid' && $row->due_date<CarbonImmutable::now('Asia/Manila')->toDateString()) $row->labels[]='Overdue salary';
        if($row->hold_reason) $row->labels[]='Payment on hold';
        return $row;
    }

    public function preview(User $actor,array $input): array
    {
        $d=Validator::make($input,['ids'=>'required|array|min:1|max:'.self::LIMIT,'ids.*'=>'required|integer|distinct|min:1','mode'=>'required|in:generate,prepare','account_id'=>'nullable|uuid'])->validate();
        $f=$this->filters($input);
        $actor=app(StaffSalary::class)->admin($actor);
        $rows=$this->query($actor->business_name,array_replace($f,['q'=>'','hub'=>null,'status'=>'','blocker'=>'']))->whereIn('u.id',$d['ids'])->orderBy('u.id')->get()->keyBy('id');
        abort_unless($rows->count()===count($d['ids']),403,'Selection contains unavailable Staff.');
        $account=null;$available=0;
        if($d['mode']==='prepare'){
            Validator::make($input,['account_id'=>'required|uuid'])->validate();
            $account=app(LogisticsTreasury::class)->account($d['account_id'],$actor->business_name);
            app(LogisticsTreasury::class)->requireGcash($account);
            $available=app(StaffSalary::class)->available($account->id);
        }
        $items=[];$total=0;
        foreach($rows as $row){
            $reason=null;$state='ready';$amount=(int)$row->installment_cents;$basisId=$row->basis_id;
            if($row->staff_status!=='approved') $reason='Suspended Staff';
            if($d['mode']==='generate'){
                if($row->period_id) $state='existing';
                elseif(!$reason){try{$terms=app(StaffSalary::class)->periodTerms($row->id,$actor->business_name,$f);$amount=$terms['amount_cents'];$basisId=$terms['salary_id'];}catch(ValidationException $e){$reason=collect($e->errors())->flatten()->first();}}
            }else{
                if(!$row->period_id) $reason='Generate the obligation first.';
                elseif($row->payout_id && $row->payout_status!=='cancelled') $state='existing';
                elseif(!$row->destination_id || $row->destination_ties>1 || $row->channel!=='gcash' || !preg_match('/^09[0-9]{9}$/D',(string)$row->account_number)) $reason=$row->destination_ties>1?'GCash accounts have identical saved timestamps; Staff must confirm a new receiving account.':'Missing or invalid GCash account';
                elseif($row->period_status!=='pending' || $amount<=0) $reason='Not a payable pending obligation.';
            }
            if($reason) $state='skipped';
            if($state==='ready') $total+=$amount;
            $items[]=['id'=>$row->id,'name'=>$row->given_names.' '.$row->last_name,'hub'=>$row->hub_name,'amount'=>$amount,'state'=>$state,'reason'=>$reason,
                'period_id'=>$row->period_id,'basis_id'=>$basisId,'salary_id'=>$row->salary_id,'payout_id'=>$row->payout_id,'payout_revision'=>$row->payout_revision,
                'destination_id'=>$row->destination_id,'destination_name'=>$row->account_name,'masked'=>$row->account_number?'*******'.substr($row->account_number,-4):null];
        }
        $payload=['actor'=>$actor->id,'company'=>$actor->business_name,'expires'=>time()+900,'mode'=>$d['mode'],'account_id'=>$account?->id,'account_label'=>$account ? $account->account_name.' / ending '.substr($account->account_number,-4) : null,'filters'=>$f,'items'=>$items,'total'=>$total];
        return $payload+['available'=>$available,'shortfall'=>$d['mode']==='prepare'?max(0,$total-$available):0,'token'=>Crypt::encryptString(json_encode($payload))];
    }

    public function execute(User $actor,string $token): array
    {
        try{$p=json_decode(Crypt::decryptString($token),true,512,JSON_THROW_ON_ERROR);}catch(DecryptException|\JsonException $e){abort(422,'Invalid payroll preview.');}
        abort_unless($p['actor']===$actor->id && $p['expires']>=time() && count($p['items'])<=self::LIMIT,409,'Payroll preview expired or belongs to another account.');
        return DB::transaction(function()use($actor,$p){
            $flow=app(StaffSalary::class);$actor=$flow->admin($actor);
            abort_unless($p['company']===$actor->business_name,403);
            // Lock the funding account for the bounded selection. Do not consume a subset when its total is underfunded.
            if($p['mode']==='prepare'){
                $account=app(LogisticsTreasury::class)->account($p['account_id'],$actor->business_name);
                app(LogisticsTreasury::class)->requireGcash($account);
                $already=DB::table('logistics_staff_payouts')->where('company_name',$actor->business_name)->whereIn('period_id',array_column($p['items'],'period_id'))->whereIn('status',['prepared','verified','paid'])->lockForUpdate()->pluck('period_id')->all();
                $required=array_sum(array_map(fn($item)=>$item['state']==='ready' && !in_array($item['period_id'],$already,true) ? $item['amount'] : 0,$p['items']));
                if($flow->available($account->id)<$required) throw ValidationException::withMessages(['payroll'=>'Insufficient funds for the complete preview. No instructions were prepared. Select a smaller group or add verified Staff funding.']);
            }
            $results=[];
            foreach($p['items'] as $item){
                if($item['state']!=='ready'){$results[]=$item;continue;}
                try{
                    $result=DB::transaction(function()use($flow,$actor,$p,$item){
                        $member=User::whereKey($item['id'])->lockForUpdate()->firstOrFail();
                        abort_unless($member->isHubStaff() && $member->status==='approved' && $member->business_name===$actor->business_name,403,'Staff authorization changed.');
                        $period=DB::table('logistics_staff_salary_periods')->where('staff_id',$member->id)->where('company_name',$actor->business_name)->where('period_year',$p['filters']['period_year'])->where('period_month',$p['filters']['period_month'])->where('period_half',$p['filters']['period_half'])->lockForUpdate()->first();
                        if($p['mode']==='generate'){
                            if($period) return 'existing';
                            $terms=$flow->periodTerms($member->id,$actor->business_name,$p['filters']);
                            abort_unless($terms['salary_id']===$item['basis_id'] && $terms['amount_cents']===$item['amount'],409,'Salary changed; preview again.');
                            $flow->generatePeriod($actor,$p['filters']+['staff_id'=>$member->id]);
                            return 'created';
                        }
                        abort_unless($period && $period->id===$item['period_id'] && $period->salary_id===$item['salary_id'] && (int)$period->amount_cents===$item['amount'],409,'Obligation changed; preview again.');
                        $old=DB::table('logistics_staff_payouts')->where('period_id',$period->id)->lockForUpdate()->first();
                        if($old && $old->status!=='cancelled') return 'existing';
                        abort_unless(($old?->revision)===$item['payout_revision'],409,'Instruction changed; preview again.');
                        $destination=DB::table('logistics_staff_destinations')->where('staff_id',$member->id)->orderByDesc('created_at')->orderByDesc('id')->lockForUpdate()->first();
                        abort_unless($destination && $destination->id===$item['destination_id'] && DB::table('logistics_staff_destinations')->where('staff_id',$member->id)->where('created_at',$destination->created_at)->count()===1,409,'Receiving account changed; preview again.');
                        $flow->prepare($actor,['period_id'=>$period->id,'account_id'=>$p['account_id'],'destination_id'=>$destination->id]);
                        return 'prepared';
                    });
                    $results[]=array_replace($item,['state'=>$result]);
                }catch(ValidationException $e){$results[]=array_replace($item,['state'=>'failed','reason'=>collect($e->errors())->flatten()->first()]);}
                catch(HttpExceptionInterface $e){$results[]=array_replace($item,['state'=>'failed','reason'=>$e->getMessage()?:'Record changed or is no longer available.']);}
                catch(\Illuminate\Database\QueryException $e){if(in_array((int)($e->errorInfo[1]??0),[1205,1213],true)) throw $e;report($e);$results[]=array_replace($item,['state'=>'failed','reason'=>'Database write failed; no changes were saved for this Staff member.']);}
            }
            return ['items'=>$results,'filters'=>$p['filters'],'mode'=>$p['mode']];
        },3);
    }
}
