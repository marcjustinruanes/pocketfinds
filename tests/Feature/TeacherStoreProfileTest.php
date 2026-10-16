<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class TeacherStoreProfileTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        Storage::fake('profile_images');
        DB::table('categories')->insert(['id' => 1, 'name' => 'Home & Living']);
    }

    private function seller(string $name = 'Maria Clara Handicrafts'): User
    {
        $n = User::count() + 1;

        return User::create(['account_type' => 'seller', 'status' => 'approved', 'business_name' => $name,
            'username' => 'store_profile_'.$n, 'email' => "storeprofile{$n}@example.com", 'given_names' => 'Maria Clara',
            'last_name' => 'Santos', 'contact_no' => '09189876543', 'region' => 'Central Luzon', 'province' => 'Bulacan',
            'municipality' => 'City of Malolos', 'barangay' => 'Anilao', 'street' => 'Artisan Street', 'category_id' => 1]);
    }

    private function data(): array
    {
        return ['business_name' => 'Maria Clara Handicrafts PH', 'store_description' => 'Handwoven bags and home decor from Bulacan artisans.',
            'contact_no' => '09189876543', 'region' => 'Central Luzon', 'province' => 'Bulacan',
            'municipality' => 'City of Malolos', 'barangay' => 'Anilao', 'street' => 'Artisan Street'];
    }

    public function test_sel_prof_001_store_settings_are_immediate_and_update_cart_store_name(): void
    {
        $seller = $this->seller();
        $other = $this->seller('RV Gadget Hub');
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Basket', 'price' => 450, 'stock' => 20]);
        $cart = CartItem::create(['buyer_id' => $other->id, 'product_id' => $product->id, 'seller_slug' => $seller->username,
            'seller' => $seller->business_name, 'name' => $product->name, 'price' => 450, 'qty' => 1]);
        $otherBefore = $other->fresh()->getAttributes();
        $this->actingAs($seller)->post('/seller/account/shop', $this->data() + ['logo' => UploadedFile::fake()->image('store_logo.png')->size(300),
            'banner' => UploadedFile::fake()->image('store_banner.jpg')->size(1843)])->assertSessionHas('shop_success', 'Store settings updated.');
        foreach ($this->data() as $field => $value) {
            $this->assertSame($value, $seller->fresh()->$field);
        }
        Storage::disk('profile_images')->assertExists([$seller->fresh()->profile_picture, $seller->fresh()->store_banner]);
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
        $this->assertSame('Maria Clara Handicrafts PH', $cart->fresh()->seller);
        $this->assertDatabaseCount('account_update_requests', 0);
    }

    public function test_sel_prof_002_003_required_fields_and_spaces_preserve_previous_values(): void
    {
        $seller = $this->seller();
        $before = $seller->fresh()->getAttributes();
        foreach (['business_name', 'contact_no', 'region', 'municipality', 'barangay', 'street'] as $field) {
            $response = $this->actingAs($seller)->postJson('/seller/account/shop', array_replace($this->data(), [$field => '   ']))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertCount(1, $response->json('errors'));
            $this->assertSame($before, $seller->fresh()->getAttributes());
        }
    }

    public function test_sel_prof_004_duplicate_names_are_trimmed_case_insensitive_and_do_not_modify_other_store(): void
    {
        $seller = $this->seller();
        $other = $this->seller('RV Gadget Hub');
        $before = $other->fresh()->getAttributes();
        foreach (['RV Gadget Hub', 'rv gadget hub', '  RV Gadget Hub  '] as $name) {
            $this->actingAs($seller)->postJson('/seller/account/shop', array_replace($this->data(), ['business_name' => $name]))
                ->assertUnprocessable()->assertJsonValidationErrors('business_name');
            $this->assertSame('Maria Clara Handicrafts', $seller->fresh()->business_name);
            $this->assertSame($before, $other->fresh()->getAttributes());
        }
    }

    public function test_sel_prof_005_name_boundaries_save_exactly_and_never_silently_truncate(): void
    {
        $seller = $this->seller();
        foreach (['MC', 'Maria Clara Handicrafts Bulacan'] as $name) {
            $this->actingAs($seller)->postJson('/seller/account/shop', array_replace($this->data(), ['business_name' => $name]))->assertUnprocessable();
        }
        foreach (['MCH', 'Maria Clara Handicrafts Bulaca'] as $name) {
            $this->post('/seller/account/shop', array_replace($this->data(), ['business_name' => $name]))->assertSessionHasNoErrors();
            $this->assertSame($name, $seller->fresh()->business_name);
        }
    }

    public function test_sel_prof_006_description_500_saves_and_501_does_not(): void
    {
        $seller = $this->seller();
        $this->actingAs($seller)->post('/seller/account/shop', array_replace($this->data(), ['store_description' => str_repeat('A', 500)]))->assertSessionHasNoErrors();
        $this->assertSame(str_repeat('A', 500), $seller->fresh()->store_description);
        $this->postJson('/seller/account/shop', array_replace($this->data(), ['store_description' => str_repeat('B', 501)]))->assertUnprocessable()->assertJsonValidationErrors('store_description');
        $this->assertSame(str_repeat('A', 500), $seller->fresh()->store_description);
    }

    public function test_sel_prof_007_accepts_both_phone_formats_and_rejects_all_four_invalid_inputs(): void
    {
        $seller = $this->seller();
        foreach (['0918987654', '091898765432', '0918-ABC-6543', '08189876543'] as $phone) {
            $this->actingAs($seller)->postJson('/seller/account/shop', array_replace($this->data(), ['contact_no' => $phone]))->assertUnprocessable()->assertJsonValidationErrors('contact_no');
        }
        foreach (['09189876543', '+639189876543'] as $phone) {
            $this->post('/seller/account/shop', array_replace($this->data(), ['contact_no' => $phone]))->assertSessionHasNoErrors();
            $this->assertSame('09189876543', $seller->fresh()->contact_no);
        }
    }

    public function test_sel_prof_008_logo_and_banner_content_types_and_size_boundaries_preserve_previous_files_on_rejection(): void
    {
        $seller = $this->seller();
        $this->actingAs($seller);
        foreach (['logo' => 'profile_picture', 'banner' => 'store_banner'] as $field => $column) {
            foreach ([5017, 5120] as $size) {
                $this->post('/seller/account/shop', $this->data() + [$field => UploadedFile::fake()->image('size.jpg')->size($size)])->assertSessionHasNoErrors();
                Storage::disk('profile_images')->assertExists($seller->fresh()->$column);
            }
            $previous = $seller->fresh()->$column;
            $files = [UploadedFile::fake()->image('size_5.1MB.jpg')->size(5223), UploadedFile::fake()->create('price_list.docx'), UploadedFile::fake()->create('installer.exe')];
            $payload = UploadedFile::fake()->createWithContent('bad.exe', 'MZ executable');
            $files[] = new UploadedFile($payload->getPathname(), 'renamed_exe.jpg', 'image/jpeg', null, true);
            foreach ($files as $file) {
                $response = $this->postJson('/seller/account/shop', $this->data() + [$field => $file])->assertUnprocessable()->assertJsonValidationErrors($field);
                $this->assertStringContainsString('JPG or PNG', $response->json('errors.'.$field.'.0'));
                $this->assertStringContainsString('5 MB', $response->json('errors.'.$field.'.0'));
                $this->assertSame($previous, $seller->fresh()->$column);
                Storage::disk('profile_images')->assertExists($previous);
            }
        }
    }

    public function test_sel_prof_013_foreign_store_id_tampering_is_rejected_without_changing_either_store(): void
    {
        $seller = $this->seller();
        $other = $this->seller('RV Gadget Hub');
        $this->actingAs($seller)->postJson('/seller/account/shop', $this->data() + ['seller_id' => $other->id])->assertForbidden();
        $this->postJson('/seller/account/shop', $this->data() + ['user_id' => $other->id])->assertForbidden();
        $this->assertSame('Maria Clara Handicrafts', $seller->fresh()->business_name);
        $this->assertSame('RV Gadget Hub', $other->fresh()->business_name);
    }
}
