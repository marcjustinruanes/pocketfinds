<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GcashSchema
{
    public const TABLES = [
        'gcash_purchases' => ['id', 'buyer_id', 'seller_id', 'checkout_id', 'request_hash', 'snapshot', 'amount_due', 'payment_method_id', 'payment_state', 'reservation_state', 'activated_at', 'expires_at', 'payment_reference', 'verified_amount', 'verified_by', 'verified_at', 'revision', 'generation', 'instructions', 'created_at', 'updated_at'],
        'gcash_stock_allocations' => ['id', 'purchase_id', 'product_id', 'product_reference', 'generation', 'option_key', 'variation_group', 'variation_value', 'quantity', 'state', 'released_at', 'created_at', 'updated_at'],
        'gcash_voucher_allocations' => ['id', 'purchase_id', 'voucher_id', 'voucher_reference', 'generation', 'state', 'created_at', 'updated_at'],
        'gcash_proofs' => ['id', 'purchase_id', 'submission_id', 'submitted_by', 'content_hash', 'path', 'mime', 'size', 'created_at'],
        'gcash_purchase_history' => ['id', 'purchase_id', 'actor_id', 'event', 'before', 'after', 'reason', 'created_at'],
        'orders' => ['gcash_purchase_id'], 'messages' => ['gcash_purchase_id'],
    ];

    public const UNIQUE = [
        'gcash_purchases' => [['payment_reference'], ['buyer_id', 'checkout_id', 'seller_id']],
        'gcash_stock_allocations' => [['purchase_id', 'generation', 'product_reference', 'option_key']],
        'gcash_voucher_allocations' => [['purchase_id', 'generation']],
        'gcash_proofs' => [['purchase_id', 'submission_id']],
        'orders' => [['gcash_purchase_id']],
    ];

    public const FOREIGN = [
        'gcash_purchases' => ['buyer_id' => 'users', 'seller_id' => 'users', 'verified_by' => 'users', 'payment_method_id' => 'payment_methods'],
        'gcash_stock_allocations' => ['purchase_id' => 'gcash_purchases', 'product_id' => 'products'],
        'gcash_voucher_allocations' => ['purchase_id' => 'gcash_purchases', 'voucher_id' => 'vouchers'],
        'gcash_proofs' => ['purchase_id' => 'gcash_purchases', 'submitted_by' => 'users'],
        'gcash_purchase_history' => ['purchase_id' => 'gcash_purchases', 'actor_id' => 'users'],
        'orders' => ['gcash_purchase_id' => 'gcash_purchases'], 'messages' => ['gcash_purchase_id' => 'gcash_purchases'],
    ];

    public static function columns(): array
    {
        $spec = [];
        $add = function ($table, $names, $type, $nullable = false) use (&$spec) {
            foreach (explode(',', $names) as $name) {
                $spec[$table][$name] = [$type, $nullable];
            }
        };
        $p = 'gcash_purchases';
        $add($p, 'id,checkout_id', 'uuid');
        $add($p, 'buyer_id,seller_id,payment_method_id', 'signed');
        $add($p, 'verified_by', 'signed', true);
        $add($p, 'request_hash', 'string:64');
        $add($p, 'snapshot', 'json');
        $add($p, 'amount_due', 'money');
        $add($p, 'payment_state', 'string:32');
        $add($p, 'reservation_state', 'string:24');
        $add($p, 'activated_at,expires_at,verified_at,created_at,updated_at', 'timestamp', true);
        $add($p, 'payment_reference', 'string:64', true);
        $add($p, 'verified_amount', 'money', true);
        $add($p, 'revision,generation', 'uint');
        $add($p, 'instructions', 'json', true);
        foreach (['gcash_stock_allocations', 'gcash_voucher_allocations'] as $t) {
            $add($t, 'id', 'identity');
            $add($t, 'purchase_id', 'uuid');
            $add($t, 'generation', 'uint');
            $add($t, 'created_at,updated_at', 'timestamp', true);
        }
        $t = 'gcash_stock_allocations';
        $add($t, 'product_id', 'uuid', true);
        $add($t, 'product_reference', 'uuid');
        $add($t, 'option_key', 'string:64');
        $add($t, 'variation_group,variation_value', 'string:255', true);
        $add($t, 'quantity', 'uint');
        $add($t, 'state', 'string:24');
        $add($t, 'released_at', 'timestamp', true);
        $t = 'gcash_voucher_allocations';
        $add($t, 'voucher_id', 'uuid', true);
        $add($t, 'voucher_reference', 'uuid');
        $add($t, 'state', 'string:16');
        $t = 'gcash_proofs';
        $add($t, 'id,purchase_id,submission_id', 'uuid');
        $add($t, 'submitted_by', 'signed');
        $add($t, 'content_hash', 'string:64');
        $add($t, 'path', 'string:255');
        $add($t, 'mime', 'string:80');
        $add($t, 'size', 'uint');
        $add($t, 'created_at', 'timestamp');
        $t = 'gcash_purchase_history';
        $add($t, 'id', 'identity');
        $add($t, 'purchase_id', 'uuid');
        $add($t, 'actor_id', 'signed', true);
        $add($t, 'event', 'string:40');
        $add($t, 'before,after', 'json');
        $add($t, 'reason', 'text', true);
        $add($t, 'created_at', 'timestamp');
        foreach (['orders', 'messages'] as $t) {
            $add($t, 'gcash_purchase_id', 'uuid', true);
        }

        return $spec;
    }

    private static function compatible(array $column, string $kind, string $driver): bool
    {
        $type = strtolower($column['type']);
        $name = strtolower($column['type_name']);
        if ($column['generation'] !== null) {
            return false;
        }
        if ($driver === 'sqlite') {
            return match (true) {
                $kind === 'uuid', str_starts_with($kind, 'string:') => $name === 'varchar',
                in_array($kind, ['identity', 'signed', 'uint']) => $name === 'integer' && ($kind !== 'identity' || $column['auto_increment']),
                $kind === 'money' => $name === 'numeric',
                $kind === 'json' => in_array($name, ['text', 'json']),
                $kind === 'timestamp' => $name === 'datetime',
                $kind === 'text' => $name === 'text',
                default => false,
            };
        }
        if ($driver !== 'mysql') {
            return false;
        }

        return match (true) {
            $kind === 'uuid' => $type === 'char(36)',
            str_starts_with($kind, 'string:') => $type === 'varchar('.substr($kind, 7).')',
            $kind === 'signed' => $name === 'bigint' && ! str_contains($type, 'unsigned') && ! $column['auto_increment'],
            $kind === 'identity' => $name === 'bigint' && str_contains($type, 'unsigned') && $column['auto_increment'],
            $kind === 'uint' => $name === 'int' && str_contains($type, 'unsigned') && ! $column['auto_increment'],
            $kind === 'money' => $type === 'decimal(12,2)',
            $kind === 'json' => $name === 'json', $kind === 'timestamp' => $type === 'timestamp', $kind === 'text' => $name === 'text',
            default => false,
        };
    }

    public static function deleteAction(string $column): string
    {
        return in_array($column, ['product_id', 'voucher_id']) ? 'set null' : 'restrict';
    }

    public static function validForeign(array $key, string $column, string $target): bool
    {
        $actions = self::deleteAction($column) === 'restrict' ? ['restrict', 'no action'] : ['set null'];

        return $key['columns'] === [$column] && $key['foreign_table'] === $target && $key['foreign_columns'] === ['id']
            && in_array(strtolower($key['on_delete']), $actions) && in_array(strtolower($key['on_update']), ['restrict', 'no action'])
            && (DB::connection()->getDriverName() !== 'mysql' || $key['foreign_schema'] === DB::connection()->getDatabaseName());
    }

    // Recovery may add missing objects, but must reject incompatible existing objects first.
    public static function problems(bool $allowMissing = false): array
    {
        $errors = [];
        $driver = DB::connection()->getDriverName();
        $spec = self::columns();
        foreach (self::TABLES as $table => $required) {
            if (! Schema::hasTable($table)) {
                if (! $allowMissing) {
                    $errors[] = 'Missing table '.$table;
                }

                continue;
            }
            $columns = collect(Schema::getColumns($table))->keyBy('name');
            foreach ($spec[$table] as $name => [$kind, $nullable]) {
                $column = $columns->get($name);
                if (! $column) {
                    if (! ($allowMissing && in_array($table, ['orders', 'messages']))) {
                        $errors[] = 'Missing column '.$table.'.'.$name;
                    }
                } elseif (! self::compatible($column, $kind, $driver) || $column['nullable'] !== $nullable) {
                    $errors[] = 'Incompatible column '.$table.'.'.$name;
                }
            }
            $indexes = Schema::getIndexes($table);
            if (! collect($indexes)->contains(fn ($index) => $index['primary'] && $index['columns'] === ['id'])) {
                $errors[] = 'Invalid primary key in '.$table;
            }
            foreach (self::UNIQUE[$table] ?? [] as $unique) {
                if (! collect($indexes)->contains(fn ($index) => $index['unique'] && $index['columns'] === $unique) && ! $allowMissing) {
                    $errors[] = 'Missing unique constraint in '.$table.': '.implode(',', $unique);
                }
            }
            $foreign = Schema::getForeignKeys($table);
            foreach (self::FOREIGN[$table] ?? [] as $column => $target) {
                $existing = collect($foreign)->filter(fn ($key) => in_array($column, $key['columns']));
                if ($existing->isEmpty()) {
                    if (! $allowMissing) {
                        $errors[] = 'Missing foreign key in '.$table.': '.$column;
                    }
                } elseif ($existing->contains(fn ($key) => ! self::validForeign($key, $column, $target))) {
                    $errors[] = 'Incompatible foreign key in '.$table.': '.$column;
                }
            }
        }

        return $errors;
    }
}
