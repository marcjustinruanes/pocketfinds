<?php

namespace App\Console\Commands;

use App\Support\ProductColor;
use App\Support\ProductVariationSet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class NormalizeProductVariants extends Command
{
    protected $signature = 'products:normalize-variants {--apply : Back up and update existing product variations}';

    protected $description = 'Replace separate color/size stock with pair stock awaiting seller quantities, and fill named color swatches';

    public function handle(): int
    {
        $changed = $pairs = $blocked = 0;
        $backupDirectory = storage_path('app/private/variant-migration/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)));
        foreach (DB::table('products')->orderBy('id')->pluck('id') as $id) {
            DB::transaction(function () use ($id, $backupDirectory, &$changed, &$pairs, &$blocked) {
                $row = DB::table('products')->where('id', $id)->lockForUpdate()->first();
                $variations = json_decode($row->variations ?? '[]', true) ?: [];
                $needsPairs = ! ProductVariationSet::combination($variations) && ProductVariationSet::hasColorAndSize($variations);
                if ($needsPairs && $this->hasReferences($id)) {
                    $blocked++;
                    $this->warn('Skipped referenced inventory: '.$row->name);
                    return;
                }
                $updated = $needsPairs ? ProductVariationSet::preparePairStock($variations) : $variations;
                foreach ($updated as &$group) {
                    if (($group['type'] ?? null) === 'combinations') {
                        foreach ($group['axes'] as &$axis) {
                            $this->fillColors($axis);
                        }
                        unset($axis);
                    } else {
                        $this->fillColors($group);
                    }
                }
                unset($group);
                if ($updated === $variations) return;
                $changed++;
                if ($needsPairs) $pairs++;
                $this->line(($this->option('apply') ? 'Updating: ' : 'Would update: ').$row->name.($needsPairs ? ' (seller must set pair quantities)' : ' (color swatches)'));
                if (! $this->option('apply')) return;
                File::ensureDirectoryExists($backupDirectory);
                $backup = $backupDirectory.'/'.$id.'.json';
                if (file_put_contents($backup, json_encode((array) $row, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                    throw new \RuntimeException('Could not back up product inventory.');
                }
                $changes = ['variations' => json_encode($updated, JSON_THROW_ON_ERROR)];
                if ($needsPairs) $changes['stock'] = 0;
                DB::table('products')->where('id', $id)->update($changes);
            });
        }
        $this->info("Products: {$changed}; converted to pair stock: {$pairs}; skipped references: {$blocked}.");
        if ($this->option('apply') && $changed) $this->info('Backup: '.$backupDirectory);

        return self::SUCCESS;
    }

    private function fillColors(array &$group): void
    {
        if (! preg_match('/^colou?rs?$/i', $group['name'])) return;
        foreach ($group['options'] as &$option) {
            $color = ProductColor::resolve($option);
            if ($color) $option['color'] = $color;
        }
    }

    private function hasReferences(string $id): bool
    {
        foreach (['cart_items', 'order_items', 'gcash_stock_allocations'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'product_id') && DB::table($table)->where('product_id', $id)->lockForUpdate()->get()->isNotEmpty()) return true;
        }
        foreach (['orders' => 'items', 'gcash_purchases' => 'snapshot'] as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column) && DB::table($table)->where($column, 'like', '%'.$id.'%')->lockForUpdate()->get()->isNotEmpty()) return true;
        }

        return false;
    }
}
