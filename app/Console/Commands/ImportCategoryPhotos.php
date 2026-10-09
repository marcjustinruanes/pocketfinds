<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCategoryPhotos extends Command
{
    protected $signature = 'categories:import-photos {directory : Directory containing the eight category JPEGs}';
    protected $description = 'Store curated category photographs directly in MySQL';

    public function handle(): int
    {
        $photos = [
            'pet' => '1583337130417-3346a1be7dee',
            'electronics' => '1498049794561-7780e7231661',
            'men' => '1617137968427-85924c800a22',
            'women' => '1483985988355-763728e1935b',
            'baby' => '1519689680058-324335c77eba',
            'beauty' => '1596462502278-27bfdc403348',
            'home' => '1416879595882-3373a0480b5b',
            'sports' => '1517649763962-0c623066013b',
        ];
        $updates = [];
        foreach (Category::get(['id', 'name']) as $category) {
            $name = mb_strtolower($category->name);
            $key = match (true) {
                str_contains($name, 'women') => 'women',
                str_contains($name, 'men') => 'men',
                str_contains($name, 'pet') => 'pet',
                str_contains($name, 'electronic') => 'electronics',
                str_contains($name, 'beauty') => 'beauty',
                str_contains($name, 'home') => 'home',
                str_contains($name, 'baby') => 'baby',
                str_contains($name, 'sport') => 'sports',
                default => null,
            };
            if (!$key) continue;
            $file = rtrim($this->argument('directory'), '/\\').DIRECTORY_SEPARATOR.$key.'.jpg';
            if (!is_file($file) || filesize($file) > 2 * 1024 * 1024 || (@getimagesize($file)[2] ?? null) !== IMAGETYPE_JPEG) {
                $this->error('Missing or invalid JPEG: '.$file);
                return self::FAILURE;
            }
            $bytes = file_get_contents($file);
            $updates[$category->id] = [
                'image_data' => $bytes,
                'image_mime' => 'image/jpeg',
                'image_sha256' => hash('sha256', $bytes),
                'image_source' => 'https://images.unsplash.com/photo-'.$photos[$key],
            ];
        }
        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $photo) DB::table('categories')->where('id', $id)->update($photo);
        });
        $this->info(count($updates).' category photographs stored in MySQL.');
        return self::SUCCESS;
    }
}
