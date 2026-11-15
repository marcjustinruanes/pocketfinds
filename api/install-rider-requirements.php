<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/bootstrap.php';
$sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../sql/mysql/2026_11_04_rider_requirements.sql'));
foreach(explode(';',$sql) as $statement) if(trim($statement)!=='') Illuminate\Support\Facades\DB::unprepared($statement);
echo "Mobile rider requirements schema installed additively.\n";
