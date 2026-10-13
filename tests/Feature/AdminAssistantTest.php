<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\AdminAssistantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAssistantTest extends TestCase
{
    private function admin(): User
    {
        return (new User(['is_admin' => true, 'given_names' => 'Admin', 'account_type' => 'buyer']))->forceFill(['id' => 10]);
    }

    private function answer(array $replace = []): array
    {
        return array_replace([
            'summary' => 'Human review is required.', 'risk' => 'needs_review',
            'findings' => [['title' => 'Check warning', 'evidence' => 'Warning not provided.', 'next_step' => 'Inspect the listing manually.']],
            'limitations' => ['Cannot certify safety.'], 'draft' => ['title' => 'Update', 'body' => 'Please review [confirm date].'],
            'source_ids' => ['product:p1', 'invented'],
        ], $replace);
    }

    private function fake(array $answer): void
    {
        config(['services.groq.key' => 'test-key', 'services.groq.model' => 'openai/gpt-oss-20b', 'services.groq.vision_model' => 'qwen/qwen3.8-27b']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($answer)]]]])]);
    }

    private function context(string $mode): array
    {
        return ['image' => null, 'data' => ['mode' => $mode, 'generated_at' => now()->toIso8601String(), 'audience' => 'seller',
            'photo_note' => 'Photos not assessed.', 'sources' => [['id' => 'product:p1', 'label' => 'Product', 'url' => route('admin.products')]]]];
    }

    public function test_guest_buyer_and_seller_cannot_access_admin_ai(): void
    {
        Http::fake();
        foreach ([null, new User(['is_admin' => false, 'account_type' => 'buyer']), new User(['is_admin' => false, 'account_type' => 'seller'])] as $user) {
            if ($user) $this->actingAs($user);
            $this->get(route('admin.ai'))->assertRedirect(route('login'));
            $this->postJson(route('admin.ai.analyze'), ['mode' => 'dashboard', 'consent' => true])->assertRedirect(route('login'));
            $this->deleteJson(route('admin.ai.clear'))->assertRedirect(route('login'));
            $this->post(route('admin.ai.prepare-draft'), ['title' => 'Test'])->assertRedirect(route('login'));
        }
        Http::assertNothingSent();
    }

    public function test_all_six_tasks_use_structured_read_only_results(): void
    {
        $this->fake($this->answer());
        $this->mock(AdminAssistantContext::class)->shouldReceive('build')->times(6)->andReturnUsing(fn ($input) => $this->context($input['mode']));
        $this->actingAs($this->admin());
        foreach (['dashboard', 'reports', 'seller', 'product', 'draft', 'chat'] as $mode) {
            $response = $this->postJson(route('admin.ai.analyze'), ['mode' => $mode, 'consent' => true, 'message' => 'Review this', 'seller_id' => 1, 'product_id' => 'p1']);
            $response->assertOk()->assertJsonCount(1, 'sources')->assertJsonPath('sources.0.url', route('admin.products'));
            $response->assertJsonPath('draft.title', $mode === 'draft' ? 'Update' : '');
        }
        Http::assertSentCount(6);
        Http::assertSent(fn ($request) => $request['messages'][0]['role'] === 'system' && !isset($request['tools']));
    }

    public function test_missing_key_and_consent_never_send_data(): void
    {
        Http::fake(); config(['services.groq.key' => null]);
        $this->actingAs($this->admin())->postJson(route('admin.ai.analyze'), ['mode' => 'dashboard', 'consent' => true])->assertStatus(503);
        $this->postJson(route('admin.ai.analyze'), ['mode' => 'dashboard', 'consent' => false])->assertUnprocessable();
        $this->postJson(route('admin.ai.analyze'), ['mode' => 'execute_sql', 'consent' => true])->assertUnprocessable();
        $this->postJson(route('admin.ai.analyze'), ['mode' => 'chat', 'consent' => true, 'message' => str_repeat('x', 1501)])->assertUnprocessable();
        $this->postJson(route('admin.ai.analyze'), ['mode' => 'product', 'consent' => true])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_business_tasks_and_product_copy_are_advisory_only(): void
    {
        $this->fake($this->answer(['draft' => ['title' => 'Reviewed title', 'body' => 'Existing specifications only.', 'tags' => ['gift'], 'category' => 'Invented category']]));
        $this->mock(AdminAssistantContext::class)->shouldReceive('build')->times(4)->andReturnUsing(fn ($input) => $this->context($input['mode']) + []);
        $this->actingAs($this->admin());
        foreach (['analytics', 'inventory', 'anomalies', 'product_copy'] as $mode) {
            $response = $this->postJson(route('admin.ai.analyze'), ['mode' => $mode, 'product_id' => 'p1', 'days' => 1, 'consent' => true]);
            $response->assertOk()->assertJsonPath('draft.title', $mode === 'product_copy' ? 'Reviewed title' : '');
            if ($mode === 'product_copy') $response->assertJsonPath('draft.category', '')->assertJsonPath('draft.tags.0', 'gift');
        }
        // No product table exists: the copy task cannot accidentally publish or edit one.
        $this->assertFalse(Schema::hasTable('products'));
        Http::assertSentCount(4);
    }

    public function test_quota_and_malformed_outputs_do_not_leak_provider_details(): void
    {
        config(['services.groq.key' => 'test-key']);
        $this->mock(AdminAssistantContext::class)->shouldReceive('build')->twice()->andReturn($this->context('dashboard'));
        Http::fake(['api.groq.com/*' => Http::sequence()->push(['error' => 'Private API details'], 429)
            ->push(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"summary":"Incomplete schema"}']]]])]);
        $this->actingAs($this->admin())->postJson(route('admin.ai.analyze'), ['mode' => 'dashboard', 'consent' => true])
            ->assertTooManyRequests()->assertDontSee('Private API details');
        $this->postJson(route('admin.ai.analyze'), ['mode' => 'dashboard', 'consent' => true])->assertStatus(503);
        Http::assertSentCount(2);
    }

    public function test_announcement_transfer_saves_only_a_session_draft_not_a_publication(): void
    {
        Http::fake();
        $this->actingAs($this->admin())->post(route('admin.ai.prepare-draft'), [
            'title' => 'Draft title', 'body' => 'Editable draft', 'audience' => 'seller',
        ])->assertRedirect(route('admin.announcements'))->assertSessionHas('ai_announcement_draft.title', 'Draft title');
        // No announcements table exists: accidental publishing would fail this test.
        $this->assertFalse(Schema::hasTable('announcements'));
        Http::assertNothingSent();
    }

    public function test_chat_uses_only_this_admins_history_and_clear_is_scoped(): void
    {
        $this->fake($this->answer());
        $this->mock(AdminAssistantContext::class)->shouldReceive('build')->once()->andReturn($this->context('chat'));
        $this->actingAs($this->admin())->withSession(['admin_assistant' => [
            10 => [['role' => 'user', 'content' => 'My earlier question']],
            11 => [['role' => 'user', 'content' => 'Another admin question']],
        ]])->postJson(route('admin.ai.analyze'), ['mode' => 'chat', 'message' => 'What now?', 'consent' => true])->assertOk();
        Http::assertSent(fn ($request) => str_contains(json_encode($request['messages']), 'My earlier question')
            && !str_contains(json_encode($request['messages']), 'Another admin question'));
        $this->deleteJson(route('admin.ai.clear'))->assertOk();
        $this->assertNull(session('admin_assistant.10'));
        $this->assertNotNull(session('admin_assistant.11'));
    }

    public function test_vision_is_requested_only_when_a_photo_is_supplied(): void
    {
        $this->fake($this->answer());
        $context = $this->context('product'); $context['image'] = 'data:image/png;base64,test';
        $this->mock(AdminAssistantContext::class)->shouldReceive('build')->once()->andReturn($context);
        $this->actingAs($this->admin())->postJson(route('admin.ai.analyze'), ['mode' => 'product', 'product_id' => 'p1', 'include_photo' => true, 'consent' => true])->assertOk();
        Http::assertSent(fn ($request) => $request['model'] === 'qwen/qwen3.8-27b'
            && $request['messages'][1]['content'][1]['type'] === 'image_url' && !isset($request['reasoning_effort']));
    }

    public function test_product_review_cannot_return_an_inapplicable_review_label(): void
    {
        $this->fake($this->answer(['risk' => 'not_applicable']));
        $this->mock(AdminAssistantContext::class)->shouldReceive('build')->once()->andReturn($this->context('product'));
        $this->actingAs($this->admin())->postJson(route('admin.ai.analyze'), ['mode' => 'product', 'product_id' => 'p1', 'consent' => true])
            ->assertOk()->assertJsonPath('risk', 'needs_review');
    }

    public function test_missing_selected_record_returns_not_found_without_a_provider_call(): void
    {
        $this->createSchema(); config(['services.groq.key' => 'test-key']); Http::fake();
        $this->actingAs($this->admin())->postJson(route('admin.ai.analyze'), ['mode' => 'product', 'product_id' => 'missing', 'consent' => true])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_workspace_view_escapes_names_and_does_not_expose_the_api_key(): void
    {
        config(['services.groq.key' => 'test-secret-key']);
        $this->actingAs($this->admin());
        $this->view('admin.ai-workspace', [
            'products' => collect([(new Product(['name' => '<script>alert(1)</script>', 'status' => 'pending']))->forceFill(['id' => 'p1'])]),
            'sellers' => collect(), 'selection' => [], 'aiAvailable' => true, 'chatHistory' => [],
            'pendingRegistrations' => 0, 'openDisputes' => 0, 'unreadMessages' => 0, 'pendingDocs' => 0, 'pendingProducts' => 0,
        ])->assertSee('AI Workspace')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('test-secret-key');
    }

    private function createSchema(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        Schema::create('users', function (Blueprint $table) {
            $table->integer('id')->primary(); $table->string('account_type'); $table->boolean('is_admin')->default(false);
            $table->string('status'); $table->string('business_name')->nullable();
            foreach (['email', 'contact_no', 'province', 'municipality', 'barangay', 'id_file', 'selfie_file', 'business_permit_file', 'category_other'] as $field) $table->string($field)->nullable();
            $table->integer('category_id')->nullable(); $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table) { $table->integer('id')->primary(); $table->string('name'); });
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('name'); $table->string('status');
            $table->integer('seller_id'); $table->integer('category_id')->nullable(); $table->integer('stock');
            foreach (['variations', 'description', 'image', 'images', 'sku', 'condition'] as $field) $table->text($field)->nullable();
            $table->decimal('price'); $table->decimal('discount_price')->nullable(); $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) { $table->integer('id')->primary(); $table->integer('seller_id'); $table->string('status'); $table->decimal('total'); $table->timestamps(); });
        Schema::create('complaints', function (Blueprint $table) { $table->string('id')->primary(); $table->string('status'); $table->string('complaint_type')->nullable(); $table->text('subject')->nullable(); $table->text('description')->nullable(); $table->timestamp('created_at'); });
        Schema::create('policies', function (Blueprint $table) { $table->integer('id')->primary(); $table->string('account_type'); $table->string('company_name')->nullable(); $table->text('content'); });
        DB::table('users')->insert(['id' => 1, 'account_type' => 'seller', 'status' => 'pending', 'business_name' => 'Example shop',
            'email' => 'private@example.com', 'contact_no' => '09123456789', 'province' => 'PRIVATE ADDRESS', 'id_file' => 'PRIVATE-DOCUMENT-PATH', 'created_at' => now()]);
        DB::table('products')->insert(['id' => 'p1', 'name' => 'Example product', 'status' => 'pending', 'seller_id' => 1, 'stock' => 2, 'price' => 100, 'created_at' => now()]);
    }

    public function test_database_snapshots_are_grounded_private_and_read_only(): void
    {
        $this->createSchema();
        DB::table('products')->insert(['id' => 'p2', 'name' => 'Active low-stock product', 'status' => 'active', 'seller_id' => 1, 'stock' => 50, 'variations' => json_encode([['options' => [['stock' => 2]]]]), 'price' => 100, 'created_at' => now()]);
        foreach ([['completed', 100], ['delivered', 200], ['cancelled', 9999], ['placed', 800]] as $i => [$status, $total]) {
            DB::table('orders')->insert(['id' => $i + 1, 'seller_id' => 1, 'status' => $status, 'total' => $total, 'created_at' => now()]);
        }
        DB::table('complaints')->insert(['id' => 'c1', 'status' => 'open', 'subject' => 'Delivery issue', 'description' => 'Email private@example.com or call 09123456789', 'created_at' => now()]);
        $builder = app(AdminAssistantContext::class);
        $summary = $builder->build(['mode' => 'dashboard', 'days' => 30])['data'];
        $this->assertSame(300.0, $summary['period_delivered_completed_order_value']);
        $this->assertSame(4, $summary['period_order_count']);
        $this->assertSame(300.0, $summary['top_shops_this_month'][0]['order_value']);
        $this->assertSame(1, $summary['low_stock_count']);
        $this->assertSame(2, $summary['low_stock_examples'][0]['stock']);
        $seller = json_encode($builder->build(['mode' => 'seller', 'seller_id' => 1])['data']);
        foreach (['private@example.com', '09123456789', 'PRIVATE ADDRESS', 'PRIVATE-DOCUMENT-PATH'] as $secret) $this->assertStringNotContainsString($secret, $seller);
        $reports = json_encode($builder->build(['mode' => 'reports'])['data']);
        $this->assertStringNotContainsString('private@example.com', $reports);
        $this->assertStringNotContainsString('09123456789', $reports);
        $this->fake($this->answer());
        $this->actingAs($this->admin())->postJson(route('admin.ai.analyze'), ['mode' => 'product', 'product_id' => 'p1', 'consent' => true])->assertOk();
        $this->assertSame('pending', Product::find('p1')->status);
        $this->assertSame('pending', User::find(1)->status);
    }

    public function test_local_photo_consent_and_path_boundaries(): void
    {
        $this->createSchema(); Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jE7kAAAAASUVORK5CYII=');
        Storage::disk('public')->put('product.png', $png);
        Product::find('p1')->update(['image' => 'product.png']);
        $builder = app(AdminAssistantContext::class);
        $this->assertNull($builder->build(['mode' => 'product', 'product_id' => 'p1', 'include_photo' => false])['image']);
        $context = $builder->build(['mode' => 'product', 'product_id' => 'p1', 'include_photo' => true]);
        $this->assertStringStartsWith('data:image/png;base64,', $context['image']);
        $this->assertSame(1, $context['data']['photos_assessed']);
        $this->actingAs($this->admin())->get(route('admin.ai.photo', 'p1'))->assertOk()->assertHeader('Content-Type', 'image/png');
        foreach (['https://169.254.169.254/latest/meta-data/', '../../../../.env', 'C:\\private.png'] as $path) {
            Product::find('p1')->update(['image' => $path]);
            $this->assertNull($builder->build(['mode' => 'product', 'product_id' => 'p1', 'include_photo' => true])['image']);
            $this->get(route('admin.ai.photo', 'p1'))->assertNotFound();
        }
        Storage::disk('public')->put('too-large.png', $png.str_repeat('x', 4 * 1024 * 1024));
        Product::find('p1')->update(['image' => 'too-large.png']);
        $this->assertNull($builder->build(['mode' => 'product', 'product_id' => 'p1', 'include_photo' => true])['image']);
    }
}
