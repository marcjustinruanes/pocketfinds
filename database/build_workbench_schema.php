<?php

$inventory = json_decode(file_get_contents(__DIR__.'/schema_inventory.json'), true, flags: JSON_THROW_ON_ERROR);
function ident(string $name): string { return '`'.str_replace('`', '``', $name).'`'; }
function constraintIdent(string $table, string $name): string {
    $full = $table.'_'.$name;
    return ident(strlen($full) <= 64 ? $full : substr($full, 0, 51).'_'.substr(sha1($full), 0, 12));
}
$tables = $constraints = $enums = $indexed = [];
foreach ($inventory['columns'] as $c) $tables[$c['table_name']][] = $c;
foreach ($inventory['constraints'] as $c) {
    $name = $c['table_name'];
    $constraints[$name][] = $c;
    if (in_array($c['constraint_type'], ['p', 'u'])) {
        preg_match('/\(([^)]+)\)/', $c['definition'], $m);
        foreach (explode(',', $m[1]) as $column) $indexed[$name][trim($column, ' "')] = true;
    }
    if ($c['constraint_type'] === 'c' && str_contains($c['definition'], 'ANY')) {
        preg_match('/CHECK \(\(+([a-z_]+)/', $c['definition'], $m);
        preg_match_all("/'(?:[^']|'')*'/", $c['definition'], $values);
        if (!$m || !$values[0]) throw new RuntimeException('Unrecognized enum check');
        $enums[$name][$m[1]] = $values[0];
    }
}
$sql = "-- PocketFinds: MySQL Workbench review model, based on live public schema.\n";
$sql .= "-- Schema only: 43 tables / 534 columns. No data migration or Supabase deletion.\n";
$sql .= "-- Import with File > Import > Reverse Engineer MySQL Create Script.\n";
$sql .= "-- Review adaptations: CHECK value lists become ENUM; UUID defaults require application generation.\n";
$sql .= "-- Unbounded numeric becomes DECIMAL(65,20); vector(48) becomes JSON (search redesign required).\n";
$sql .= "-- Timestamptz becomes DATETIME(6); convert existing data to UTC on migration.\n";
$sql .= "-- Indexed PostgreSQL text becomes VARCHAR(255); validate existing lengths before migration.\n";
$sql .= "-- RLS, functions, triggers, and non-constraint indexes are outside this review model.\n\n";
$sql .= "CREATE SCHEMA IF NOT EXISTS `pocketfinds_review` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nUSE `pocketfinds_review`;\nSET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
foreach ($tables as $name => $columns) {
    $lines = [];
    foreach ($columns as $c) {
        $column = $c['column_name'];
        $type = $c['data_type'];
        $note = '';
        if (isset($enums[$name][$column])) $type = 'ENUM('.implode(', ', $enums[$name][$column]).')';
        elseif (preg_match('/^character varying\((\d+)\)$/', $type, $m)) $type = 'VARCHAR('.$m[1].')';
        elseif (preg_match('/^numeric\((\d+),(\d+)\)$/', $type, $m)) $type = 'DECIMAL('.$m[1].','.$m[2].')';
        elseif (preg_match('/^timestamp\((\d+)\) without time zone$/', $type, $m)) $type = 'DATETIME('.$m[1].')';
        else $type = match ($type) {
            'uuid' => 'CHAR(36)', 'bigint' => 'BIGINT', 'integer' => 'INT', 'smallint' => 'SMALLINT',
            'boolean' => 'BOOLEAN', 'text', 'character varying' => isset($indexed[$name][$column]) ? 'VARCHAR(255)' : 'LONGTEXT',
            'json', 'jsonb', 'vector(48)' => 'JSON', 'numeric' => 'DECIMAL(65,20)',
            'date' => 'DATE', 'timestamp with time zone', 'timestamp without time zone' => 'DATETIME(6)',
            default => throw new RuntimeException('Unhandled type '.$type),
        };
        $line = '  '.ident($column).' '.$type.($c['nullable'] ? ' NULL' : ' NOT NULL');
        $default = $c['column_default'];
        if ($default !== null) {
            if (str_starts_with($default, 'nextval(')) $line .= ' AUTO_INCREMENT';
            elseif ($default === 'gen_random_uuid()') $line .= ' DEFAULT (UUID())';
            elseif ($default === 'now()') $line .= ' DEFAULT CURRENT_TIMESTAMP'.($type === 'DATETIME(6)' ? '(6)' : '');
            else {
                $default = preg_replace('/::(?:character varying|text|numeric|boolean)$/', '', $default);
                if ($type === 'LONGTEXT' || $type === 'JSON') $line .= ' DEFAULT ('.$default.')';
                else $line .= ' DEFAULT '.$default;
            }
        }
        if ($c['data_type'] === 'vector(48)') $note = 'PostgreSQL vector(48); JSON array of 48 numbers; redesign vector search';
        if ($note) $line .= " COMMENT '".str_replace("'", "''", $note)."'";
        $lines[] = $line;
    }
    foreach ($constraints[$name] ?? [] as $c) {
        $definition = $c['definition'];
        if (in_array($c['constraint_type'], ['p','u'])) {
            preg_match('/\(([^)]+)\)/', $definition, $m);
            $cols = implode(', ', array_map(fn ($v) => ident(trim($v, ' "')), explode(',', $m[1])));
            $lines[] = '  '.($c['constraint_type'] === 'p' ? 'PRIMARY KEY' : 'UNIQUE KEY '.ident($c['constraint_name'])).' ('.$cols.')';
        } elseif ($c['constraint_type'] === 'f') {
            if (!preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES ([a-z_]+)\(([^)]+)\)(.*)$/', $definition, $m)) throw new RuntimeException('Unhandled FK');
            $cols = fn ($s) => implode(', ', array_map(fn ($v) => ident(trim($v, ' "')), explode(',', $s)));
            $lines[] = '  CONSTRAINT '.constraintIdent($name, $c['constraint_name']).' FOREIGN KEY ('.$cols($m[1]).') REFERENCES '.ident($m[2]).' ('.$cols($m[3]).')'.$m[4];
        } elseif ($c['constraint_type'] === 'c' && !str_contains($definition, 'ANY')) {
            // Retain simple numeric/range checks; ENUM above carries the value-list checks.
            $definition = preg_replace('/::numeric/', '', $definition);
            $lines[] = '  CONSTRAINT '.constraintIdent($name, $c['constraint_name']).' '.$definition;
        }
    }
    $sql .= 'CREATE TABLE '.ident($name)." (\n".implode(",\n", $lines)."\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;\n\n";
}
$sql .= "SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;\n";
$sql .= "\n".file_get_contents(__DIR__.'/mysql-extra-indexes.sql');
file_put_contents(__DIR__.'/pocketfinds-workbench-schema.sql', $sql);
echo 'Created Workbench schema: '.count($tables).' tables, '.count($inventory['columns']).' columns.'.PHP_EOL;
