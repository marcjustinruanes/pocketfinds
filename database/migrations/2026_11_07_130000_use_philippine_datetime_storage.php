<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->shift('ADD');
    }

    public function down(): void
    {
        $this->shift('SUB');
    }

    private function shift(string $operation): void
    {
        // Legacy PHP wrote UTC wall-clock strings using MySQL's default session.
        // DATETIME needs +8h. TIMESTAMP also needs correction for the old server
        // offset, because it previously interpreted those UTC strings as local.
        // DATE, TIME and epoch counters must not be shifted.
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('SET time_zone = @@global.time_zone');
        try {
            $legacyOffset = (int) DB::selectOne('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS seconds')->seconds;
        } finally {
            DB::statement("SET time_zone = '+08:00'");
        }
        $columns = DB::select("SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, DATA_TYPE AS data_type
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('datetime', 'timestamp')
            AND EXTRA NOT LIKE '%GENERATED%'");

        DB::transaction(function () use ($columns, $operation, $legacyOffset) {
            foreach (collect($columns)->groupBy('table_name') as $table => $fields) {
                $quote = fn ($name) => '`'.str_replace('`', '``', $name).'`';
                $updates = $fields->map(function ($field) use ($quote, $operation, $legacyOffset) {
                    $seconds = $field->data_type === 'datetime' ? 28800 : $legacyOffset;
                    return $quote($field->column_name).' = DATE_'.$operation.'('.$quote($field->column_name).', INTERVAL '.$seconds.' SECOND)';
                });
                DB::statement('UPDATE '.$quote($table).' SET '.$updates->implode(', '));
            }
            // Delivery promises were also snapshotted as naive UTC strings.
            foreach ([['orders', 'commerce_snapshot', '$.delivery_policy'], ['gcash_purchases', 'snapshot', '$.commerce.delivery_policy']] as [$table, $column, $root]) {
                if (! \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) continue;
                foreach (['from', 'by'] as $key) {
                    $path = $root.'.'.$key;
                    DB::update("UPDATE `$table` SET `$column` = JSON_SET(`$column`, ?, DATE_FORMAT(DATE_{$operation}(STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(`$column`, ?)), '%Y-%m-%d %H:%i:%s'), INTERVAL 8 HOUR), '%Y-%m-%d %H:%i:%s')) WHERE JSON_UNQUOTE(JSON_EXTRACT(`$column`, ?)) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$'", [$path, $path, $path]);
                }
            }
        });
    }
};
