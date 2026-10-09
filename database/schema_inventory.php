<?php

// Read-only inventory: no application records or credentials are exported.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $db = Illuminate\Support\Facades\DB::connection();
    if ($db->getDriverName() !== 'pgsql') {
        throw new RuntimeException('Expected the configured PostgreSQL connection.');
    }
    $db->statement('SET statement_timeout = 20000');
    $columns = $db->select("SELECT n.nspname AS schema_name, c.relname AS table_name, a.attname AS column_name, pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type, NOT a.attnotnull AS nullable, pg_get_expr(d.adbin, d.adrelid) AS column_default FROM pg_catalog.pg_attribute a JOIN pg_catalog.pg_class c ON c.oid = a.attrelid JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace LEFT JOIN pg_catalog.pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum WHERE n.nspname = 'public' AND c.relkind IN ('r','p') AND a.attnum > 0 AND NOT a.attisdropped ORDER BY c.relname, a.attnum");
    $constraints = $db->select("SELECT c.relname AS table_name, con.conname AS constraint_name, con.contype AS constraint_type, pg_get_constraintdef(con.oid) AS definition FROM pg_catalog.pg_constraint con JOIN pg_catalog.pg_class c ON c.oid = con.conrelid JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' ORDER BY c.relname, con.conname");
    file_put_contents(__DIR__.'/schema_inventory.json', json_encode(['captured_at' => gmdate('c'), 'source' => 'Live configured PostgreSQL database; public schema only', 'columns' => $columns, 'constraints' => $constraints], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo 'Exported '.count($columns).' columns from '.count(array_unique(array_column($columns, 'table_name')))." tables.\n";
} catch (Throwable $e) {
    // Avoid printing connection strings or credentials from exception messages.
    fwrite(STDERR, 'Inventory failed: '.get_class($e).".\n");
    exit(1);
}
