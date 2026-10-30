<?php
namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HubStaffSalary
{
    public function installed(): bool { return Schema::hasTable('logistics_hub_salary_rates') && Schema::hasColumn('logistics_staff_salaries','period_effective'); }
    public function periodStart(): CarbonImmutable { $n=CarbonImmutable::now('Asia/Manila');return $n->startOfMonth()->setDay($n->day<=15?1:16); }
    public function nextPeriod(): CarbonImmutable { $n=$this->periodStart();return $n->day===1?$n->setDay(16):$n->addMonth()->startOfMonth(); }

    public function configure(User $actor,array $input): void
    {
        abort_unless($this->installed(),503,'Hub salary and attendance migration required.');
        $d=Validator::make($input,['hub_id'=>'required|integer','monthly'=>'required|string','revision'=>'required|integer|min:0'])->validate();
        $amount=app(LogisticsFinance::class)->cents($d['monthly']);abort_unless($amount>0,422,'Monthly salary must be positive.');
        DB::transaction(function()use($actor,$d,$amount){
            $actor=app(StaffSalary::class)->admin($actor);
            $hub=DB::table('logistics_hubs')->where('id',$d['hub_id'])->where('company_name',$actor->business_name)->lockForUpdate()->firstOrFail();
            $revision=DB::table('logistics_hub_salary_rates')->where('hub_id',$hub->id)->max('revision')??0;
            abort_unless((int)$revision===(int)$d['revision'],409,'Hub salary changed; refresh.');
            $id=(string)Str::uuid();$effective=$this->nextPeriod()->toDateString();
            DB::table('logistics_hub_salary_rates')->insert(['id'=>$id,'company_name'=>$actor->business_name,'hub_id'=>$hub->id,'revision'=>$revision+1,'monthly_cents'=>$amount,'effective_period'=>$effective,'set_by'=>$actor->id,'created_at'=>now()]);
            $members=User::where('business_name',$actor->business_name)->where('logistics_role','hub_staff')->where('account_type','logistics')->where('is_logistics',true)->where('logistics_hub_id',$hub->id)->whereIn('status',['approved','suspended'])->orderBy('id')->lockForUpdate()->get();
            foreach($members as $member){
                $last=DB::table('logistics_staff_salaries')->where('staff_id',$member->id)->orderByDesc('revision')->first();
                if(!$last || $last->salary_source==='hub') $this->inherit($member,$effective,$actor->id);
            }
            app(LogisticsFinance::class)->event($actor->business_name,'hub_salary_configured','hub_salary',$id,['hub_id'=>$hub->id,'monthly_cents'=>$amount,'effective_period'=>$effective],$actor->id);
        },3);
    }

    public function inherit(User $member,string $effective,?int $actor=null): void
    {
        $rate=DB::table('logistics_hub_salary_rates')->where('company_name',$member->business_name)->where('hub_id',$member->logistics_hub_id)->where('effective_period','<=',$effective)->orderByDesc('revision')->lockForUpdate()->first();
        $last=DB::table('logistics_staff_salaries')->where('staff_id',$member->id)->orderByDesc('revision')->lockForUpdate()->first();
        if($last && $last->salary_source==='hub' && $last->source_hub_id==$member->logistics_hub_id && $last->hub_rate_id===$rate?->id && $last->period_effective===$effective) return;
        DB::table('logistics_staff_salaries')->where('staff_id',$member->id)->whereNull('superseded_at')->update(['superseded_at'=>now()]);
        $id=(string)Str::uuid();
        DB::table('logistics_staff_salaries')->insert(['id'=>$id,'company_name'=>$member->business_name,'staff_id'=>$member->id,'monthly_cents'=>$rate?->monthly_cents??0,
            'revision'=>($last?->revision??0)+1,'set_by'=>$actor??$rate?->set_by??$member->id,'effective_from'=>now(),'period_effective'=>$effective,'salary_source'=>'hub','source_hub_id'=>$member->logistics_hub_id,'hub_rate_id'=>$rate?->id,'created_at'=>now(),'updated_at'=>now()]);
        app(LogisticsFinance::class)->event($member->business_name,'staff_hub_salary_inherited','staff_salary',$id,['hub_id'=>$member->logistics_hub_id,'hub_rate_id'=>$rate?->id,'effective_period'=>$effective,'monthly_cents'=>$rate?->monthly_cents??null],$actor);
    }

    // Called inside the existing approval/transfer transaction, after the account save.
    public function membershipChanged(User $member,?object $before): void
    {
        if(!$this->installed() || !$member->isHubStaff() || $member->status!=='approved') return;
        if($before && $before->status==='approved' && $before->logistics_hub_id==$member->logistics_hub_id) return;
        $last=DB::table('logistics_staff_salaries')->where('staff_id',$member->id)->orderByDesc('revision')->lockForUpdate()->first();
        $transfer=$before && $before->logistics_hub_id!=$member->logistics_hub_id;
        if($transfer) app(LogisticsFinance::class)->event($member->business_name,'staff_salary_hub_changed','staff',(string)$member->id,['from_hub'=>$before->logistics_hub_id,'to_hub'=>$member->logistics_hub_id,'salary_source'=>$last?->salary_source??'hub'],auth()->id());
        if($last && $last->salary_source==='individual') return;
        $effective=$last || $transfer ? $this->nextPeriod()->toDateString() : $this->periodStart()->toDateString();
        $this->inherit($member,$effective,auth()->id());
        // New approvals also inherit an already configured upcoming hub revision.
        $upcoming=DB::table('logistics_hub_salary_rates')->where('hub_id',$member->logistics_hub_id)->where('company_name',$member->business_name)->where('effective_period','>',$effective)->orderBy('effective_period')->orderByDesc('revision')->first();
        if($upcoming) $this->inherit($member,$upcoming->effective_period,auth()->id());
    }

    public function useHub(User $actor,array $input): void
    {
        abort_unless($this->installed(),503);
        $d=Validator::make($input,['staff_id'=>'required|integer','revision'=>'required|integer|min:0','confirmed'=>'required|accepted'])->validate();
        DB::transaction(function()use($actor,$d){
            $actor=app(StaffSalary::class)->admin($actor);
            $member=User::whereKey($d['staff_id'])->lockForUpdate()->firstOrFail();
            abort_unless($member->isHubStaff() && $member->status==='approved' && $member->business_name===$actor->business_name,403);
            $rev=DB::table('logistics_staff_salaries')->where('staff_id',$member->id)->max('revision')??0;
            abort_unless((int)$rev===(int)$d['revision'],409,'Salary changed; refresh.');
            $this->inherit($member,$this->nextPeriod()->toDateString(),$actor->id);
        },3);
    }
}
