<?php

$inventory = json_decode(file_get_contents(__DIR__.'/schema_inventory.json'), true, flags: JSON_THROW_ON_ERROR);
function mysqlType(string $type): string
{
    if (preg_match('/^character varying\((\d+)\)$/', $type, $m)) return 'VARCHAR('.$m[1].')';
    if (preg_match('/^numeric\((\d+),(\d+)\)$/', $type, $m)) return 'DECIMAL('.$m[1].','.$m[2].')';
    if (preg_match('/^timestamp\((\d+)\) without time zone$/', $type, $m)) return 'DATETIME('.$m[1].')';
    if (str_starts_with($type, 'vector(')) return 'JSON (requires vector/search redesign)';
    return match ($type) {
        'bigint' => 'BIGINT', 'integer' => 'INT', 'smallint' => 'SMALLINT',
        'boolean' => 'BOOLEAN (TINYINT(1))', 'uuid' => 'CHAR(36)',
        'text', 'character varying' => 'LONGTEXT (review size)',
        'json', 'jsonb' => 'JSON', 'date' => 'DATE',
        'timestamp without time zone' => 'DATETIME(6)',
        'timestamp with time zone' => 'DATETIME(6), normalize to UTC',
        'double precision' => 'DOUBLE', 'real' => 'FLOAT',
        'numeric' => 'DECIMAL (choose precision/scale)',
        default => 'REVIEW: '.$type,
    };
}
function cell(mixed $value): string
{
    return str_replace(["|", "\r", "\n"], ['\\|', '', ' '], (string) $value);
}
$tables = [];
foreach ($inventory['columns'] as $column) $tables[$column['table_name']][] = $column;
$md = '# PocketFinds database review'."\n\n";
$md .= 'Source: live configured Supabase/PostgreSQL database, `public` schema. Captured '.$inventory['captured_at'].'. '.count($tables).' tables, '.count($inventory['columns'])." columns. No records exported and no database changes made. Supabase-managed schemas such as auth/storage are outside this application-table inventory.\n\n";
$md .= "## Before choosing what to remove\n\n";
$md .= "- MySQL Workbench is a client; the database will reside on a MySQL server.\n- MySQL types below are proposed equivalents, not executable migration SQL. IDs and foreign keys must use matching signedness. PostgreSQL sequences/UUID defaults, casts, CHECK constraints, RLS policies, and timestamp defaults need separate conversion.\n- Current migrations include duplicate table creation and PostgreSQL-specific SQL. Do not run the existing migrations unchanged against MySQL.\n- `rider_profiles`: User.php describes this as a previously merged/dropped table, but it still exists live. Review for retirement after checking data and dependencies.\n- `document_update_requests`: legacy table still exists alongside `account_update_requests`; review whether its records have been carried forward.\n- `message_reactions` and `messages.reactions`: two reaction representations coexist; choose one after checking usage and retained data.\n- `users.age` duplicates information derivable from birthday. Several identity/document path columns also overlap; verify which paths are used before removing them.\n- `orders` contains legacy/new pairs including buyer_id/app_buyer_id, seller_id/app_seller_id, and shipping_fee/shipping_amount. Review their types, constraints, and application usage before consolidating.\n- `products.image`, `products.images`, and `product_images` coexist. Determine the intended primary/gallery storage before removing any.\n- `cache`/`cache_locks` and `sessions` are Laravel infrastructure. Removal depends on cache/session driver configuration. `migrations` tracks migration history.\n- The live public schema has no `order_items` table despite `reviews.order_item_id` and an OrderItem model. Resolve this mismatch when designing the target schema.\n- The following report lists structure only. It does not establish that a table is empty or safe to delete. Back up and validate the MySQL import before removing Supabase.\n\n";
$md .= "## Table index\n\n| Table | Columns | Decision |\n| --- | ---: | --- |\n";
foreach ($tables as $name => $columns) $md .= '| ['.$name.'](#'.str_replace('_', '-', $name).') | '.count($columns)." | Keep / Change / Remove |\n";
$csv = fopen(__DIR__.'/schema-review.csv', 'w');
fputcsv($csv, ['table','column','postgres_type','suggested_mysql_type','nullable','postgres_default','decision','notes'], escape: '');
foreach ($tables as $name => $columns) {
    $md .= "\n## ".$name."\n\n| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |\n| --- | --- | --- | --- | --- |\n";
    foreach ($columns as $c) {
        $row = [$c['column_name'], $c['data_type'], mysqlType($c['data_type']), $c['nullable'] ? 'Yes' : 'No', $c['column_default'] ?? '—'];
        $md .= '| '.implode(' | ', array_map('cell', $row))." |\n";
        fputcsv($csv, [$name, $c['column_name'], $c['data_type'], mysqlType($c['data_type']), $c['nullable'] ? 'Yes' : 'No', $c['column_default'] ?? '', '', ''], escape: '');
    }
    $constraints = array_filter($inventory['constraints'], fn ($c) => $c['table_name'] === $name);
    if ($constraints) {
        $md .= "\nConstraints (current PostgreSQL definitions):\n\n";
        foreach ($constraints as $c) $md .= '- `'.$c['constraint_name'].'`: `'.cell($c['definition'])."`\n";
    }
}
fclose($csv);
file_put_contents(__DIR__.'/schema-review.md', $md);
echo 'Created schema-review.md and schema-review.csv.'.PHP_EOL;
