<?php
namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A founder reservation does not restrict company personnel rows. */
class CompanyOwnership
{
    public static function founder(User $u): bool { return $u->account_type==='logistics' && $u->is_logistics && in_array($u->logistics_role,[null,'admin'],true); }
    public static function mutex(string $name): string
    {
        $key=mb_strtolower(trim($name));
        if(DB::getDriverName()==='mysql'){
            $c=DB::selectOne("SELECT COLLATION_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='business_name'")->c;
            if(!preg_match('/^[a-zA-Z0-9_]+$/D',$c))throw new \RuntimeException('Unsupported company collation.');
            $key=DB::selectOne('SELECT HEX(WEIGHT_STRING(CONVERT(? USING utf8mb4) COLLATE '.$c.')) AS k',[$key])->k;
        }
        return 'company:'.$key;
    }
    public function persist(User $u,Closure $save): bool
    {
        return DB::transaction(function()use($u,$save){
            $name=trim((string)$u->business_name);
            if($name==='')throw ValidationException::withMessages(['business_name'=>'A company name is required.']);
            $bucket=hexdec(substr(hash('sha256',mb_strtolower(self::mutex($name))),0,2));
            DB::table('hiring_identity_locks')->where('id',$bucket)->lockForUpdate()->first();
            if($u->exists){
                $fresh=DB::table('users')->where('id',$u->id)->lockForUpdate()->first();
                foreach(['account_type','business_name','logistics_role','status'] as $field)if($fresh->{$field}!=$u->getRawOriginal($field))throw ValidationException::withMessages(['status'=>'Founder application changed. Refresh before deciding.']);
                if((bool)$fresh->is_logistics!==(bool)$u->getRawOriginal('is_logistics'))throw ValidationException::withMessages(['status'=>'Founder authorization changed. Refresh.']);
            }
            $other=User::where('account_type','logistics')->where('is_logistics',true)->where(fn($q)=>$q->where('logistics_role','admin')->orWhereNull('logistics_role'))
                ->when($u->exists,fn($q)=>$q->whereKeyNot($u->id))->whereRaw('LOWER(TRIM(business_name)) = ?',[mb_strtolower($name)])->lockForUpdate()->first();
            if($other)throw ValidationException::withMessages(['business_name'=>'This company already has a founder application or authorized Admin.']);
            try{return $save();}catch(\Illuminate\Database\UniqueConstraintViolationException $e){
                if(str_contains($e->getMessage(),'company_owner_unique') || str_contains($e->getMessage(),'company_owner_key'))throw ValidationException::withMessages(['business_name'=>'This company already has a founder application or authorized Admin.']);
                throw $e;
            }
        },3);
    }
}
