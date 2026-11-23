<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/bootstrap.php';
use Illuminate\Support\Facades\DB;
if (in_array('--down',$argv,true)) {
    // Refuse to discard operational records; rollback is only safe before use.
    foreach (['delivery_events','delivery_attempts','delivery_verifications'] as $table) {
        if (Illuminate\Support\Facades\Schema::hasTable($table) && DB::table($table)->exists()) {
            throw new RuntimeException('Rollback refused: delivery records exist. Export and migrate them before rollback.');
        }
    }
    foreach (['delivery_events','delivery_attempts','delivery_verifications'] as $table) DB::statement("DROP TABLE IF EXISTS $table");
    echo "Unused rider schema rolled back.\n"; exit;
}
$sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../sql/mysql/2026_10_29_rider_workflow.sql'));
foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') DB::statement($statement);
if (!Illuminate\Support\Facades\Schema::hasColumn('delivery_events','result_error')) {
    Illuminate\Support\Facades\Schema::table('delivery_events',fn($t)=>$t->string('result_error')->nullable());
}
echo "Additive rider schema installed. Existing rows retained.\n";
