<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        $duplicates=DB::table('users')->where('account_type','logistics')->where('is_logistics',1)->where(fn($q)=>$q->where('logistics_role','admin')->orWhereNull('logistics_role'))->whereNotNull('business_name')
            ->selectRaw('LOWER(TRIM(business_name)) AS company_key, COUNT(*) AS owners')->groupByRaw('LOWER(TRIM(business_name))')->havingRaw('COUNT(*) > 1')->exists();
        if($duplicates)throw new RuntimeException('Duplicate company founders require authorized review before installing ownership protection. No account was changed.');
        if(!Schema::hasColumn('users','company_owner_key'))Schema::table('users',function(Blueprint $t){
            $column=$t->string('company_owner_key',255);
            if(DB::getDriverName()==='mysql')$column->collation(DB::selectOne("SELECT COLLATION_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='business_name'")->c);
            $column->virtualAs("CASE WHEN account_type = 'logistics' AND is_logistics = 1 AND (logistics_role = 'admin' OR logistics_role IS NULL) THEN LOWER(TRIM(business_name)) ELSE NULL END");
        });
        if(!in_array('company_owner_unique',array_column(Schema::getIndexes('users'),'name'),true))Schema::table('users',fn(Blueprint $t)=>$t->unique('company_owner_key','company_owner_unique'));
    }
    public function down(): void { throw new RuntimeException('Company ownership protection requires an explicit forward migration.'); }
};
