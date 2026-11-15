<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class SharePublicImages extends Command
{
    protected $signature = 'images:share';
    protected $description = 'Store public storefront images in the database and export an image-only MySQL import file';
    public function handle(): int
    {
        $directory = base_path('handoff');
        if (!is_dir($directory)) mkdir($directory, 0755, true);
        $output = fopen($directory.'/PocketFinds-images.sql', 'wb');
        if (!$output) throw new \RuntimeException('Cannot write image SQL file.');
        $count = 0;
        try {
            fwrite($output, "-- Public storefront images only. Import into the existing PocketFinds database.\nSET NAMES utf8mb4;\nCREATE TABLE IF NOT EXISTS public_image_assets (path VARCHAR(255) PRIMARY KEY, mime VARCHAR(32) NOT NULL, sha256 CHAR(64) NOT NULL, data_base64 LONGTEXT NOT NULL);\n");
            $disk = Storage::disk('public');
            foreach (['product_images','hero_banners','logistics_logos','profiles/shop-photos','profiles/shop-banners'] as $folder) {
                foreach ($disk->files($folder) as $path) {
                    if (!preg_match('~^[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|gif|avif)$~i', basename($path))) continue;
                    $bytes = $disk->get($path);
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                    if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif','image/avif'], true)) continue;
                    $row = ['path'=>$path, 'mime'=>$mime, 'sha256'=>hash('sha256', $bytes), 'data_base64'=>base64_encode($bytes)];
                    DB::table('public_image_assets')->updateOrInsert(['path'=>$path], $row);
                    // Every value is constrained ASCII or base64; no user SQL is interpolated.
                    $values = implode(',', array_map(fn($value)=>"'".$value."'", array_values($row)));
                    fwrite($output, 'INSERT INTO public_image_assets (path,mime,sha256,data_base64) VALUES ('.$values.') ON DUPLICATE KEY UPDATE mime=VALUES(mime),sha256=VALUES(sha256),data_base64=VALUES(data_base64);'."\n");
                    $count++;
                }
            }
        } finally { fclose($output); }
        $this->info($count.' images stored. Share handoff/PocketFinds-images.sql with the updated application code.');
        return self::SUCCESS;
    }
}
