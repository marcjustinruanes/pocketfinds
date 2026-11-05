<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StoreAuthCollageImage extends Command
{
    protected $signature = 'auth:store-collage-image {path : Local JPEG image to store} {--slot=main : main, back, or front}';
    protected $description = 'Store the sign-in collage image in platform settings';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (!is_file($path) || !is_readable($path) || filesize($path) > 5 * 1024 * 1024
            || (@getimagesize($path)[2] ?? null) !== IMAGETYPE_JPEG) {
            $this->error('Choose a readable JPEG image of at most 5 MB.');
            return self::FAILURE;
        }
        $data = file_get_contents($path);
        if (!isset(\App\Support\AuthCollage::SLOTS[$this->option('slot')])) {
            $this->error('Choose main, back, or front.');
            return self::FAILURE;
        }
        $prefix = \App\Support\AuthCollage::prefix($this->option('slot'));
        DB::table('settings')->where('id', Setting::current()->id)->update([
            $prefix.'data' => $data,
            $prefix.'mime' => 'image/jpeg',
            $prefix.'sha256' => hash('sha256', $data),
            'updated_at' => now(),
        ]);
        $this->info('Sign-in collage image stored in the database ('.strlen($data).' bytes).');
        return self::SUCCESS;
    }
}
