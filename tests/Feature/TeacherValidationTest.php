<?php

namespace Tests\Feature;

use App\Models\BuyerAddress;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

/** HTTP regression checks derived from the teacher's workbooks. UI checks are separate. */
class TeacherValidationTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('public');
        DB::table('categories')->insert(['id' => 1, 'name' => 'Home']);
    }

    private function person(string $role = 'buyer', string $status = 'approved'): User
    {
        $serial = User::count() + 1;

        return User::create(['account_type' => $role, 'status' => $status, 'given_names' => 'Juan Miguel',
            'last_name' => 'Dela Cruz', 'username' => 'teacher_'.$serial, 'email' => 'teacher'.$serial.'@example.com',
            'password' => 'Mamimili@2026', 'category_id' => 1, 'business_name' => 'Teacher Store '.$serial]);
    }

    private function productData(): array
    {
        return ['name' => 'Handwoven Basket', 'description' => 'A durable handwoven basket for the home.',
            'price' => '450.00', 'stock' => 20, 'weight_grams' => 800, 'length_cm' => 20,
            'width_cm' => 20, 'height_cm' => 15, 'condition' => 'new',
            'images' => [UploadedFile::fake()->image('basket.png')]];
    }

    public function test_buy_acct_003_correct_old_password_changes_password_and_rejects_old_login(): void
    {
        $buyer = $this->person();
        $this->actingAs($buyer)->post('/buyer/account/password', ['current_password' => 'Mamimili@2026',
            'password' => 'Mamimili@2027', 'password_confirmation' => 'Mamimili@2027'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Mamimili@2027', $buyer->fresh()->password));
        $this->assertFalse(Hash::check('Mamimili@2026', $buyer->fresh()->password));
        auth()->logout();
        $this->post('/login', ['email' => $buyer->email, 'password' => 'Mamimili@2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $buyer->email, 'password' => 'Mamimili@2027'])->assertRedirect('/buyer/dashboard');
        $this->assertAuthenticatedAs($buyer);
    }

    public function test_buy_acct_004_wrong_old_password_never_changes_password(): void
    {
        $buyer = $this->person();
        $before = $buyer->password;
        $this->actingAs($buyer)->post('/buyer/account/password', ['current_password' => 'wrong',
            'password' => 'Mamimili@2027', 'password_confirmation' => 'Mamimili@2027'])->assertSessionHasErrors('current_password');
        $this->assertSame($before, $buyer->fresh()->password);
    }

    public function test_buy_acct_005_all_invalid_new_passwords_leave_hash_unchanged(): void
    {
        $buyer = $this->person();
        $before = $buyer->password;
        foreach (['Mami@26', 'mamimili@2027', 'MAMIMILI@2027', 'Mamimili@abcd', 'Mamimili2027', 'Mamimili@2026'] as $password) {
            $this->actingAs($buyer)->postJson('/buyer/account/password', ['current_password' => 'Mamimili@2026',
                'password' => $password, 'password_confirmation' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->assertSame($before, $buyer->fresh()->password);
        }
        $this->postJson('/buyer/account/password', ['current_password' => 'Mamimili@2026',
            'password' => 'Mamimili@2027', 'password_confirmation' => 'Mamimili@2028'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame($before, $buyer->fresh()->password);
    }

    public function test_buy_addr_005_tenth_address_saves_but_eleventh_does_not(): void
    {
        $buyer = $this->person();
        $data = ['recipient_name' => 'Juan Miguel Dela Cruz', 'contact_no' => '09171234567',
            'region' => 'NCR', 'province' => 'Metro Manila', 'municipality' => 'Makati City', 'barangay' => 'Bel-Air', 'street' => 'Office Street'];
        for ($i = 0; $i < 9; $i++) {
            BuyerAddress::create($data + ['buyer_id' => $buyer->id, 'is_default' => $i === 0]);
        }
        $this->actingAs($buyer)->postJson('/buyer/addresses', $data)->assertOk();
        $this->assertSame(10, BuyerAddress::where('buyer_id', $buyer->id)->count());
        $this->postJson('/buyer/addresses', $data)->assertUnprocessable();
        $this->assertSame(10, BuyerAddress::where('buyer_id', $buyer->id)->count());
    }

    public function test_buy_addr_007_default_cannot_be_deleted(): void
    {
        $buyer = $this->person();
        $address = BuyerAddress::create(['buyer_id' => $buyer->id, 'recipient_name' => 'Juan', 'is_default' => true]);
        $this->actingAs($buyer)->deleteJson('/buyer/addresses/'.$address->id)->assertUnprocessable();
        $this->assertTrue($address->fresh()->is_default);
    }

    public function test_buy_addr_009_foreign_address_cannot_be_deleted(): void
    {
        $owner = $this->person();
        $other = $this->person();
        $address = BuyerAddress::create(['buyer_id' => $owner->id, 'recipient_name' => 'Owner', 'is_default' => false]);
        $this->actingAs($other)->deleteJson('/buyer/addresses/'.$address->id)->assertForbidden();
        $this->assertNotNull($address->fresh());
        $this->patchJson('/buyer/addresses/'.$address->id.'/default')->assertForbidden();
        $this->assertFalse($address->fresh()->is_default);
    }

    public function test_buy_addr_006_only_selected_address_becomes_default_and_old_default_can_be_deleted(): void
    {
        $buyer = $this->person();
        $first = BuyerAddress::create(['buyer_id' => $buyer->id, 'recipient_name' => 'Juan', 'is_default' => true]);
        $second = BuyerAddress::create(['buyer_id' => $buyer->id, 'recipient_name' => 'Juan Office', 'is_default' => false]);
        $this->actingAs($buyer)->patchJson('/buyer/addresses/'.$second->id.'/default')->assertOk();
        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(1, BuyerAddress::where('buyer_id', $buyer->id)->where('is_default', true)->count());
        $this->deleteJson('/buyer/addresses/'.$first->id)->assertOk();
        $this->deleteJson('/buyer/addresses/'.$second->id)->assertUnprocessable();
    }

    public function test_sel_prod_004_price_boundaries_and_precision(): void
    {
        $seller = $this->person('seller');
        $this->actingAs($seller);
        foreach (['0', '-50', '0.99', '199.999', '1000000.01', 'abc', ''] as $value) {
            $this->postJson(route('seller.inventory.store'), array_replace($this->productData(), ['price' => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors('price');
            $this->assertSame(0, Product::count());
        }
        foreach (['1.00', '1.01', '999999.99', '1000000.00'] as $value) {
            $this->post(route('seller.inventory.store'), array_replace($this->productData(), ['price' => $value]))->assertSessionHasNoErrors();
            $this->assertEquals((float) $value, (float) Product::query()->get()->last()->price);
        }
        $this->assertSame(4, Product::count());
    }

    public function test_sel_prod_005_stock_is_an_integer_between_zero_and_99999(): void
    {
        $this->actingAs($this->person('seller'));
        foreach ([-1, 100000, 2.5] as $value) {
            $this->postJson(route('seller.inventory.store'), array_replace($this->productData(), ['stock' => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors('stock');
        }
        $this->assertSame(0, Product::count());
        foreach ([0, 1, 99999] as $value) {
            $this->post(route('seller.inventory.store'), array_replace($this->productData(), ['stock' => $value]))->assertSessionHasNoErrors();
        }
        $this->assertSame([0, 1, 99999], Product::pluck('stock')->map(fn ($n) => (int) $n)->all());
    }

    public function test_sel_prod_008_zero_negative_and_nonnumeric_dimensions_rejected(): void
    {
        $this->actingAs($this->person('seller'));
        foreach (['weight_grams', 'length_cm', 'width_cm', 'height_cm'] as $field) {
            foreach ([0, -1, 'abc'] as $value) {
                $this->postJson(route('seller.inventory.store'), array_replace($this->productData(), [$field => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        $this->assertSame(0, Product::count());
        $this->post(route('seller.inventory.store'), array_replace($this->productData(), ['weight_grams' => 10]))->assertSessionHasNoErrors();
        $this->assertSame(10, (int) Product::firstOrFail()->weight_grams);
    }

    public function test_sel_vou_004_code_format_is_enforced_and_codes_are_uppercase(): void
    {
        $this->actingAs($this->person('seller'));
        foreach (['ABC', 'ABCDEFGHIJKLM', 'MCH 50', 'MCH-50'] as $value) {
            $this->postJson('/seller/vouchers', ['code' => $value, 'type' => 'amount', 'discount_amount' => 50])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
        }
        $this->assertSame(0, Voucher::count());
        foreach (['ABCD', 'ABCDEFGHIJKL', 'mch75'] as $value) {
            $this->post('/seller/vouchers', ['code' => $value, 'type' => 'amount', 'discount_amount' => 50])->assertSessionHasNoErrors();
            $this->assertTrue(Voucher::where('code', strtoupper($value))->exists());
        }
        $this->assertSame(3, Voucher::count());
    }
}
