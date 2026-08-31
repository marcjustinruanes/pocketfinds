<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateFilesToSupabase extends Command
{
    protected $signature   = 'files:migrate-to-supabase';
    protected $description = 'Upload existing local product images to Supabase';

    public function handle(): int
    {
        $uploaded = 0;
        $missing  = 0;

        Product::each(function (Product $p) use (&$uploaded, &$missing) {
            $paths = array_filter(array_merge(
                [$p->image, $p->video],
                (array) ($p->images ?? []),
                collect($p->variations ?? [])
                    ->flatMap(fn ($v) => collect($v['options'] ?? [])->pluck('image'))
                    ->filter()
                    ->all(),
            ));

            foreach ($paths as $path) {
                if (Storage::disk('supabase')->exists($path)) {
                    continue;
                }
                if (!Storage::disk('public')->exists($path)) {
                    $this->warn("Missing locally: {$path}");
                    $missing++;
                    continue;
                }
                Storage::disk('supabase')->writeStream(
                    $path,
                    Storage::disk('public')->readStream($path)
                );
                $this->line("Uploaded: {$path}");
                $uploaded++;
            }
        });

        $this->info("Done. Uploaded: {$uploaded}, Missing locally: {$missing}");
        return 0;
    }
}
