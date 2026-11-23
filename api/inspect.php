<?php
// Read-only schema inspection used during integration. No credentials or rows.
require __DIR__.'/bootstrap.php';
foreach (Illuminate\Support\Facades\DB::select('SHOW TABLES') as $table) {
    $name = array_values((array)$table)[0];
    $columns = Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM `'.str_replace('`', '``', $name).'`');
    echo $name.': '.implode(', ', array_map(fn ($c) => $c->Field, $columns)).PHP_EOL;
}
