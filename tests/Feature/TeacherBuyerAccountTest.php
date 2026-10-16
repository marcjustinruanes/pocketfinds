<?php

namespace Tests\Feature;

use App\Mail\RegistrationCode;
use App\Models\BuyerAddress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class TeacherBuyerAccountTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        (require database_path('migrations/2026_10_16_000001_add_account_verification.php'))->up();
        Schema::table('account_verification_tokens', fn (Blueprint $table) => $table->string('pending_value')->nullable());
        Mail::fake();
        Http::preventStrayRequests();
        Storage::fake('profile_images');
    }

    private function buyer(): User
    {
        $number = User::count() + 1;

        return User::create(['given_names' => 'Juan Miguel', 'last_name' => 'Dela Cruz', 'account_type' => 'buyer',
            'email' => "buyer{$number}@example.com", 'username' => 'teacher_buyer_'.$number, 'status' => 'approved',
            'contact_no' => '0917123456'.$number, 'password' => 'Mamimili@2026']);
    }

    private function addressData(): array
    {
        return ['label' => 'Office', 'recipient_name' => 'Juan Miguel Dela Cruz', 'contact_no' => '09171234567',
            'region' => 'NCR', 'province' => 'Metro Manila', 'municipality' => 'Makati City',
            'barangay' => 'Bel-Air', 'house_no' => '14F', 'street' => 'Pacific Tower'];
    }

    private function lastCode(string $email): string
    {
        return Mail::sent(RegistrationCode::class, fn ($mail) => $mail->hasTo($email))->last()->code;
    }

    public function test_buy_acct_001_profile_is_immediate_photo_persists_and_contact_tampering_is_ignored(): void
    {
        $buyer = $this->buyer();
        $this->actingAs($buyer)->post('/buyer/account/profile', ['given_names' => 'Juan Miguel Jr.', 'last_name' => 'Dela Cruz',
            'profile_picture' => UploadedFile::fake()->image('review_photo.jpg'), 'email' => 'attacker@example.com', 'contact_no' => '09170000001'])
            ->assertSessionHas('profile_success', 'Profile updated.');
        $buyer->refresh();
        $this->assertSame('Juan Miguel Jr.', $buyer->given_names);
        $this->assertSame('buyer1@example.com', $buyer->email);
        $this->assertSame('09171234561', $buyer->contact_no);
        Storage::disk('profile_images')->assertExists($buyer->profile_picture);
        $this->assertDatabaseCount('account_update_requests', 0);
        auth()->logout();
        $this->post('/login', ['email' => $buyer->email, 'password' => 'Mamimili@2026'])->assertRedirect('/buyer/dashboard');
        $this->get('/buyer/account')->assertOk()->assertSee('Juan Miguel Jr.')->assertSee($buyer->profile_picture);
    }

    public function test_buy_acct_002_each_invalid_profile_input_preserves_saved_profile(): void
    {
        $buyer = $this->buyer();
        foreach (['', 'Juan123', 'J'] as $name) {
            $this->actingAs($buyer)->postJson('/buyer/account/profile', ['given_names' => $name, 'last_name' => 'Dela Cruz'])
                ->assertUnprocessable()->assertJsonValidationErrors('given_names');
        }
        $files = [UploadedFile::fake()->image('large.jpg')->size(5223)];
        $fake = UploadedFile::fake()->createWithContent('payload.exe', 'MZ executable, not an image');
        $files[] = new UploadedFile($fake->getPathname(), 'renamed_exe.jpg', 'image/jpeg', null, true);
        foreach ($files as $file) {
            $this->postJson('/buyer/account/profile', ['given_names' => 'Changed Name', 'last_name' => 'Dela Cruz', 'profile_picture' => $file])
                ->assertUnprocessable()->assertJsonValidationErrors('profile_picture');
        }
        $this->assertSame('Juan Miguel', $buyer->fresh()->given_names);
        $this->assertNull($buyer->fresh()->profile_picture);
        $this->assertSame([], Storage::disk('profile_images')->allFiles());
    }

    public function test_buy_acct_006_old_email_works_until_verification_then_only_new_email_works(): void
    {
        $buyer = $this->buyer();
        $this->actingAs($buyer)->post('/buyer/account/contact/email/send', ['value' => 'buyer.test01@example.com'])->assertSessionHas('contact_success');
        $code = $this->lastCode('buyer.test01@example.com');
        $this->assertSame('buyer1@example.com', $buyer->fresh()->email);
        auth()->logout();
        $this->post('/login', ['email' => 'buyer1@example.com', 'password' => 'Mamimili@2026'])->assertRedirect('/buyer/dashboard');
        $this->get('/buyer/account')->assertSee('buyer1@example.com');
        $this->post('/buyer/account/contact/email/verify', ['code' => $code])->assertSessionHas('contact_success');
        $this->assertSame('buyer.test01@example.com', $buyer->fresh()->email);
        auth()->logout();
        $this->post('/login', ['email' => 'buyer1@example.com', 'password' => 'Mamimili@2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'buyer.test01@example.com', 'password' => 'Mamimili@2026'])->assertRedirect('/buyer/dashboard');
        $this->assertAuthenticatedAs($buyer);
        $this->postJson('/buyer/account/contact/email/verify', ['code' => $code])->assertUnprocessable();
    }

    public function test_buy_acct_007_wrong_and_expired_code_cannot_change_email_and_resend_invalidates_old_code(): void
    {
        $buyer = $this->buyer();
        $this->actingAs($buyer)->post('/buyer/account/contact/email/send', ['value' => 'new@example.com']);
        $code = $this->lastCode('new@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';
        $this->postJson('/buyer/account/contact/email/verify', ['code' => $wrong])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseHas('account_verification_tokens', ['user_id' => $buyer->id, 'attempts' => 1]);
        $this->travel(5)->minutes();
        $this->postJson('/buyer/account/contact/email/verify', ['code' => $code])->assertUnprocessable();
        $this->assertSame('buyer1@example.com', $buyer->fresh()->email);
        $this->post('/buyer/account/contact/email/send', ['value' => 'new@example.com']);
        $freshCode = $this->lastCode('new@example.com');
        $this->assertNotSame($code, $freshCode);
        $this->postJson('/buyer/account/contact/email/verify', ['code' => $code])->assertUnprocessable();
        $this->post('/buyer/account/contact/email/verify', ['code' => $freshCode])->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $buyer->fresh()->email);
    }

    public function test_buy_acct_008_duplicate_emails_across_roles_and_case_send_no_code(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer();
        $other->update(['email' => 'anamarie.reyes@example.com']);
        $seller = $this->buyer();
        $seller->update(['account_type' => 'seller', 'email' => 'mariaclara.santos@example.com']);
        foreach (['anamarie.reyes@example.com', 'MARIACLARA.SANTOS@example.com'] as $email) {
            $this->actingAs($buyer)->postJson('/buyer/account/contact/email/send', ['value' => $email])->assertUnprocessable()->assertJsonValidationErrors('value');
        }
        Mail::assertNothingSent();
        $this->assertDatabaseCount('account_verification_tokens', 0);
    }

    public function test_buy_acct_009_phone_is_normalized_unique_and_saved_only_after_code(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer();
        $other->update(['contact_no' => '+639173456789']);
        foreach (['0917123', '09173456789'] as $phone) {
            $this->actingAs($buyer)->postJson('/buyer/account/contact/contact_no/send', ['value' => $phone])->assertUnprocessable();
        }
        Mail::assertNothingSent();
        $this->post('/buyer/account/contact/contact_no/send', ['value' => '+639170000001'])->assertSessionHasNoErrors();
        $code = $this->lastCode($buyer->email);
        $this->assertSame('09171234561', $buyer->fresh()->contact_no);
        $this->post('/buyer/account/contact/contact_no/verify', ['code' => $code])->assertSessionHasNoErrors();
        $this->assertSame('09170000001', $buyer->fresh()->contact_no);
    }

    public function test_contact_taken_while_code_pending_is_rechecked_and_other_buyer_cannot_consume_code(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer();
        $this->actingAs($buyer)->post('/buyer/account/contact/email/send', ['value' => 'new@example.com']);
        $code = $this->lastCode('new@example.com');
        $this->actingAs($other)->postJson('/buyer/account/contact/email/verify', ['code' => $code])->assertUnprocessable();
        $other->update(['email' => 'new@example.com']);
        $this->actingAs($buyer)->postJson('/buyer/account/contact/email/verify', ['code' => $code])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->assertSame('buyer1@example.com', $buyer->fresh()->email);
    }

    public function test_buy_addr_001_second_address_keeps_original_default_and_all_values(): void
    {
        $buyer = $this->buyer();
        $first = BuyerAddress::create($this->addressData() + ['buyer_id' => $buyer->id, 'is_default' => true]);
        $this->actingAs($buyer)->postJson('/buyer/addresses', $this->addressData())->assertOk()->assertJsonPath('address.is_default', false);
        $second = BuyerAddress::latest('id')->first();
        foreach ($this->addressData() as $field => $value) {
            $this->assertSame($value, $second->$field);
        }
        $this->assertTrue($first->fresh()->is_default);
    }

    public function test_buy_addr_003_each_required_field_blocks_without_creating_address(): void
    {
        $buyer = $this->buyer();
        foreach (['recipient_name', 'contact_no', 'region', 'municipality', 'barangay', 'street'] as $field) {
            $this->actingAs($buyer)->postJson('/buyer/addresses', array_replace($this->addressData(), [$field => '']))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('buyer_addresses', 0);
    }

    public function test_buy_addr_004_only_valid_local_and_international_phone_formats_save(): void
    {
        $buyer = $this->buyer();
        foreach (['0917123', '091712345678'] as $phone) {
            $this->actingAs($buyer)->postJson('/buyer/addresses', array_replace($this->addressData(), ['contact_no' => $phone]))->assertUnprocessable();
        }
        foreach (['09171234567', '+639171234567'] as $phone) {
            $this->postJson('/buyer/addresses', array_replace($this->addressData(), ['contact_no' => $phone]))->assertOk()->assertJsonPath('address.contact_no', '09171234567');
        }
        $this->assertDatabaseCount('buyer_addresses', 2);
    }

    public function test_buy_addr_008_edit_and_delete_never_modify_an_existing_order_snapshot(): void
    {
        $buyer = $this->buyer();
        $address = BuyerAddress::create($this->addressData() + ['buyer_id' => $buyer->id, 'is_default' => false]);
        $snapshot = $address->toShippingArray();
        $order = Order::create(['buyer_id' => $buyer->id, 'seller_id' => $buyer->id, 'status' => 'placed', 'shipping_address' => $snapshot, 'items' => []]);
        $this->actingAs($buyer)->patchJson('/buyer/addresses/'.$address->id, array_replace($this->addressData(), ['street' => '14F Pacific Tower']))->assertOk();
        $this->assertSame('14F Pacific Tower', $address->fresh()->street);
        $this->deleteJson('/buyer/addresses/'.$address->id)->assertOk();
        $this->assertSame($snapshot, $order->fresh()->shipping_address);
    }

    public function test_buy_addr_009_foreign_address_cannot_be_read_or_edited(): void
    {
        $owner = $this->buyer();
        $other = $this->buyer();
        $address = BuyerAddress::create($this->addressData() + ['buyer_id' => $owner->id]);
        $this->actingAs($other)->getJson('/buyer/addresses/'.$address->id)->assertForbidden()->assertDontSee('Pacific Tower');
        $this->patchJson('/buyer/addresses/'.$address->id, array_replace($this->addressData(), ['street' => 'Stolen']))->assertForbidden();
        $this->assertSame('Pacific Tower', $address->fresh()->street);
    }
}
