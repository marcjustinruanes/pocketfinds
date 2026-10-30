<?php

namespace App\Http\Controllers;

use App\Services\LogisticsFinance;
use App\Services\LogisticsReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LogisticsReconciliationController extends LogisticsController
{
    private function filters(Request $r): array
    {
        app(LogisticsFinance::class)->admin($r->user());
        $today=CarbonImmutable::now('Asia/Manila');
        $data=$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d',
            'dataset'=>['nullable',Rule::in(array_keys(LogisticsReconciliation::DATASETS))],'page'=>'nullable|integer|min:1|max:100000']);
        $data+=['from'=>$today->subDays(29)->toDateString(),'to'=>$today->toDateString(),'dataset'=>'transactions','page'=>1];
        foreach(['from'=>$today->subDays(29)->toDateString(),'to'=>$today->toDateString(),'dataset'=>'transactions','page'=>1] as $key=>$default)if($data[$key]===null)$data[$key]=$default;
        abort_unless($data['from']<=$data['to'] && CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to']))<=366,422,'Choose an ordered activity period of at most 366 days. Current balances are not date-filtered.');
        return $data;
    }
    public function index(Request $r,LogisticsReconciliation $flow)
    {
        $filters=$this->filters($r);$report=$flow->snapshot($r->user(),$filters['from'],$filters['to']);$all=$report['rows'][$filters['dataset']];
        $records=new LengthAwarePaginator(array_slice($all,($filters['page']-1)*25,25),count($all),25,$filters['page'],['path'=>$r->url(),'query'=>$r->except('page')]);
        return response()->view('logistics.reconciliation',array_merge($this->sidebarCounts(),compact('report','filters','records')))
            ->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
    }
    public function export(Request $r,LogisticsReconciliation $flow)
    {
        $filters=$this->filters($r);$report=$flow->snapshot($r->user(),$filters['from'],$filters['to']);$rows=$report['rows'][$filters['dataset']];
        abort_if(count($rows)>20000,422,'Export is limited to 20,000 rows. Narrow the activity period or choose another dataset. No partial export was generated.');
        $columns=[];foreach($rows as $row)$columns=array_values(array_unique(array_merge($columns,array_keys($row))));
        if(!$columns)$columns=['id','at','status'];
        // Audit access to an export without recording its private contents or changing financial balances.
        DB::transaction(function()use($r,$filters,$report,$rows){$f=app(LogisticsFinance::class);$actor=$f->admin($r->user());
            abort_unless($actor->business_name===$report['company'],403,'Company changed. Refresh before exporting.');
            $f->event($actor->business_name,'reconciliation_exported','report',(string)Str::uuid(),['dataset'=>$filters['dataset'],'from'=>$filters['from'],'to'=>$filters['to'],'row_count'=>count($rows),'snapshot_at'=>$report['generated_at']],$actor->id);
        });
        return response()->streamDownload(function()use($rows,$columns,$report,$filters){
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['company','snapshot_at','record_timezone','period_from_manila','period_to_manila','dataset',...$columns],',','"','');
            foreach($rows as $row){$values=[];foreach($columns as $column)$values[]=LogisticsReconciliation::csvCell($row[$column]??'');
                fputcsv($out,[LogisticsReconciliation::csvCell($report['company']),$report['generated_at'],config('app.timezone'),$filters['from'],$filters['to'],$filters['dataset'],...$values],',','"','');
            }fclose($out);
        },'logistics-'.$filters['dataset'].'-'.$filters['to'].'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
