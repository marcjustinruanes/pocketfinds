<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void { $this->shift('ADD'); }
    public function down(): void { $this->shift('SUB'); }

    private function shift(string $operation): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) return;
        DB::statement('SET time_zone = @@global.time_zone');
        try {
            $offset = (int) DB::selectOne('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS seconds')->seconds;
        } finally {
            DB::statement("SET time_zone = '+08:00'");
        }
        // DEFAULT_GENERATED means a CURRENT_TIMESTAMP default, not a computed
        // column. Include those columns, which the first conversion excluded.
        $columns = DB::select("SELECT TABLE_NAME AS t, COLUMN_NAME AS c, DATA_TYPE AS type
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
            AND DATA_TYPE IN ('timestamp', 'datetime') AND EXTRA LIKE '%DEFAULT_GENERATED%'
            AND EXTRA NOT LIKE '%VIRTUAL GENERATED%' AND EXTRA NOT LIKE '%STORED GENERATED%'");
        DB::transaction(function () use ($columns, $operation, $offset) {
            $quote = fn ($name) => '`'.str_replace('`', '``', $name).'`';
            foreach (collect($columns)->groupBy('t') as $table => $fields) {
                $updates = $fields->map(fn ($field) => $quote($field->c).' = DATE_'.$operation.'('.$quote($field->c).', INTERVAL '.($field->type === 'datetime' ? 28800 : $offset).' SECOND)');
                // Preserve already-converted ON UPDATE columns in the same row.
                foreach (DB::select("SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND EXTRA LIKE '%on update%' AND EXTRA NOT LIKE '%DEFAULT_GENERATED%'", [$table]) as $field) {
                    $updates->push($quote($field->c).' = '.$quote($field->c));
                }
                DB::statement('UPDATE '.$quote($table).' SET '.$updates->implode(', '));
            }
        });
    }
};
