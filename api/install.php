<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/bootstrap.php';
$sql = file_get_contents(__DIR__.'/../sql/mysql/2026_10_29_mobile_api.sql');
$sql = preg_replace('/^--.*$/m', '', $sql);
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
foreach (explode(';', $sql) as $statement) {
    if (trim($statement) !== '') {
        $query = $pdo->prepare($statement);
        $query->execute();
        $query->closeCursor();
    }
}
echo "Mobile API schema installed in pocketfinds. Existing data retained.\n";
