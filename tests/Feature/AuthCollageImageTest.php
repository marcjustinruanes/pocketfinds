<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Http\UploadedFile;
use App\Models\User;
use App\Support\AuthCollage;
use Tests\TestCase;

class AuthCollageImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_01_120000_add_auth_collage_image_to_settings.php'))->up();
        (require database_path('migrations/2026_11_01_130000_add_auth_collage_slots_and_crops.php'))->up();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_admin')->default(false);
            $table->string('account_type')->default('buyer');
            $table->timestamps();
        });
    }

    public function test_serves_database_bytes_and_revalidates_when_the_image_changes(): void
    {
        $bytes = file_get_contents(public_path('images/auth/gift-preview.jpg'));
        $hash = hash('sha256', $bytes);
        DB::table('settings')->insert(['id' => 1, 'auth_collage_image_data' => $bytes,
            'auth_collage_image_mime' => 'image/jpeg', 'auth_collage_image_sha256' => $hash]);
        $this->get(route('auth.collage-image'))->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertContent($bytes);
        $this->withHeader('If-None-Match', '"'.$hash.'"')->get(route('auth.collage-image'))->assertStatus(304);
        DB::table('settings')->where('id', 1)->update(['auth_collage_image_data' => 'replacement', 'auth_collage_image_sha256' => hash('sha256', 'replacement')]);
        $this->withHeader('If-None-Match', '"'.$hash.'"')->get(route('auth.collage-image'))->assertOk()->assertContent('replacement');
    }

    public function test_missing_image_or_unexpected_mime_is_not_served(): void
    {
        $this->get(route('auth.collage-image'))->assertNotFound();
        DB::table('settings')->insert(['id' => 1, 'auth_collage_image_data' => '<svg/>', 'auth_collage_image_mime' => 'image/svg+xml']);
        $this->get(route('auth.collage-image'))->assertNotFound();
    }

    public function test_only_platform_admin_can_replace_images_and_save_each_slots_crop(): void
    {
        DB::table('settings')->insert(['id' => 1]);
        $url = route('admin.settings.auth-collage.update', 'back');
        $this->post($url)->assertRedirect(route('login'));
        $buyer = User::create(['account_type' => 'buyer', 'is_admin' => false]);
        $this->actingAs($buyer)->post($url)->assertRedirect(route('login'));
        $admin = User::create(['account_type' => 'admin', 'is_admin' => true]);
        $bytes = file_get_contents(public_path('images/auth/beauty-discount-preview.jpg'));
        $this->actingAs($admin)->post($url, [
            'collage_image' => UploadedFile::fake()->createWithContent('beauty.jpg', $bytes),
            'crop_x' => 30, 'crop_y' => 60, 'crop_zoom' => 140,
        ])->assertRedirect(route('admin.settings'))->assertSessionHasNoErrors()->assertSessionHas('settings_tab', 'collage');
        $row = DB::table('settings')->first();
        $this->assertSame($bytes, $row->auth_collage_back_image_data);
        $this->assertSame('image/jpeg', $row->auth_collage_back_image_mime);
        $this->assertSame(hash('sha256', $bytes), $row->auth_collage_back_image_sha256);
        $this->assertSame($admin->id, $row->updated_by);
        $crops = json_decode($row->auth_collage_crops, true);
        $this->assertSame(['x' => 30, 'y' => 60, 'zoom' => 140], $crops['back']);
        $this->get(route('auth.collage-slot', 'back'))->assertOk()->assertContent($bytes);
        $this->post(route('admin.settings.auth-collage.update', 'front'), [
            'crop_x' => 80, 'crop_y' => 20, 'crop_zoom' => 100,
        ])->assertSessionHasNoErrors();
        $this->post($url, ['crop_x' => 50, 'crop_y' => 50, 'crop_zoom' => 100])->assertSessionHasNoErrors();
        $row = DB::table('settings')->first();
        $this->assertSame($bytes, $row->auth_collage_back_image_data);
        $this->assertSame(['x' => 80, 'y' => 20, 'zoom' => 100], json_decode($row->auth_collage_crops, true)['front']);
        $this->assertStringContainsString('object-position: 30% 60%;', AuthCollage::style($crops, 'back'));
        $this->assertStringContainsString('scale(1.4)', AuthCollage::style($crops, 'back'));
    }

    public function test_invalid_upload_and_crop_cannot_mutate_saved_images(): void
    {
        DB::table('settings')->insert(['id' => 1, 'auth_collage_back_image_data' => 'original']);
        $admin = User::create(['account_type' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.settings.auth-collage.update', 'back'), [
            'collage_image' => UploadedFile::fake()->createWithContent('not-an-image.jpg', '<?php echo 1;'),
            'crop_x' => 101, 'crop_y' => -1, 'crop_zoom' => 999,
        ])->assertSessionHasErrors(['collage_image', 'crop_x', 'crop_y', 'crop_zoom'])->assertSessionHas('settings_tab', 'collage');
        $this->assertSame('original', DB::table('settings')->value('auth_collage_back_image_data'));
        $this->get('/auth/collage-image/invalid')->assertNotFound();
    }

    public function test_import_command_stores_each_file_in_the_selected_slot(): void
    {
        DB::table('settings')->insert(['id' => 1]);
        $path = public_path('images/auth/folded-outfit-preview.jpg');
        $this->artisan('auth:store-collage-image', ['path' => $path, '--slot' => 'front'])->assertSuccessful();
        $bytes = file_get_contents($path);
        $this->assertSame($bytes, DB::table('settings')->value('auth_collage_front_image_data'));
        $this->get(route('auth.collage-slot', 'front'))->assertOk()->assertContent($bytes);
        $this->assertArrayNotHasKey('auth_collage_front_image_data', \App\Models\Setting::first()->toArray());
    }
}
