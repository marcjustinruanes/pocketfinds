<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class PublicImageDatabaseTest extends TestCase
{
    public function test_database_images_load_without_local_files_and_private_paths_are_blocked(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        (require database_path('migrations/2026_11_15_120000_create_public_image_assets.php'))->up();
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9xkAAAAASUVORK5CYII=');
        $row=['mime'=>'image/png','sha256'=>hash('sha256',$bytes),'data_base64'=>base64_encode($bytes)];
        DB::table('public_image_assets')->insert(['path'=>'product_images/database-only.png']+$row);
        $this->get('/storage/product_images/database-only.png')->assertOk()->assertHeader('Content-Type','image/png')->assertContent($bytes);
        $this->withHeader('If-None-Match','"'.$row['sha256'].'"')->get('/storage/product_images/database-only.png')->assertStatus(304);
        DB::table('public_image_assets')->insert(['path'=>'id_files/private.png']+$row);
        $this->get('/storage/id_files/private.png')->assertForbidden();
        $this->get('/storage/product_images/missing.png')->assertNotFound();
        DB::table('public_image_assets')->where('path','product_images/database-only.png')->update(['sha256'=>str_repeat('0',64)]);
        $this->get('/storage/product_images/database-only.png')->assertNotFound();
    }
}
