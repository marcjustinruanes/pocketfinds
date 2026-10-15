<?php

namespace Tests\Feature;

use App\Models\AccountUpdateRequest;
use App\Models\User;
use App\Services\MediaLifecycle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class MediaLifecycleTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        foreach (['public', 'messages', 'profile_images'] as $disk) {
            Storage::fake($disk);
        }
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
    }

    private function seller(string $name = 'seller'): User
    {
        return User::create(['username' => $name, 'account_type' => 'seller', 'email' => $name.'@example.test', 'given_names' => 'Test', 'last_name' => 'Seller', 'status' => 'approved', 'password' => 'TestPassword123', 'profile_picture' => 'avatars/old.jpg']);
    }

    public function test_profile_replacement_waits_for_commit_and_rollback_preserves_old_file(): void
    {
        $user = $this->seller();
        Storage::disk('profile_images')->put($user->profile_picture, 'old');
        $this->actingAs($user);
        DB::beginTransaction();
        $this->post(route('seller.account.shop-photo'), ['profile_picture' => $this->image('new.jpg')])->assertRedirect();
        $new = $user->fresh()->profile_picture;
        $this->assertNotSame('avatars/old.jpg', $new);
        Storage::disk('profile_images')->assertExists(['avatars/old.jpg', $new]);
        DB::rollBack();
        $this->assertSame('avatars/old.jpg', $user->fresh()->profile_picture);
        Storage::disk('profile_images')->assertExists('avatars/old.jpg');
        Storage::disk('profile_images')->assertMissing($new);
    }

    public function test_successful_replacement_deletes_only_unreferenced_old_file(): void
    {
        $user = $this->seller();
        Storage::disk('profile_images')->put('avatars/old.jpg', 'old');
        $this->actingAs($user)->post(route('seller.account.shop-photo'), ['profile_picture' => $this->image('new.jpg'), 'user_id' => 999])->assertRedirect();
        Storage::disk('profile_images')->assertMissing('avatars/old.jpg');
        Storage::disk('profile_images')->assertExists($user->fresh()->profile_picture);
        $other = $this->seller('other');
        $other->update(['profile_picture' => $user->fresh()->profile_picture]);
        $shared = $other->profile_picture;
        $this->post(route('seller.account.shop-photo'), ['profile_picture' => $this->image('again.jpg'), 'user_id' => $other->id])->assertRedirect();
        Storage::disk('profile_images')->assertExists($shared);
        $this->assertSame($shared, $other->fresh()->profile_picture);
    }

    public function test_failed_database_update_removes_upload_and_preserves_original(): void
    {
        $user = $this->seller();
        Storage::disk('profile_images')->put('avatars/old.jpg', 'old');
        DB::unprepared("CREATE TRIGGER reject_profile BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $this->actingAs($user)->postJson(route('seller.account.shop-photo'), ['profile_picture' => $this->image('new.jpg')])->assertStatus(500);
        $this->assertSame('avatars/old.jpg', $user->fresh()->profile_picture);
        $this->assertSame(['avatars/old.jpg'], Storage::disk('profile_images')->allFiles());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_invalid_upload_and_wrong_role_do_not_change_files(): void
    {
        $user = $this->seller();
        Storage::disk('profile_images')->put('avatars/old.jpg', 'old');
        $this->actingAs($user)->postJson(route('seller.account.shop-photo'), ['profile_picture' => UploadedFile::fake()->create('bad.txt')])->assertUnprocessable();
        $buyer = User::create(['username' => 'buyer', 'account_type' => 'buyer', 'password' => 'TestPassword123']);
        $this->actingAs($buyer)->postJson(route('seller.account.shop-photo'), ['profile_picture' => $this->image('new.jpg'), 'user_id' => $user->id])->assertForbidden();
        $this->assertSame('avatars/old.jpg', $user->fresh()->profile_picture);
        $this->assertSame(['avatars/old.jpg'], Storage::disk('profile_images')->allFiles());
    }

    public function test_pending_document_replacement_rolls_back_then_cleans_up_on_success(): void
    {
        $user = $this->seller();
        $request = AccountUpdateRequest::create(['user_id' => $user->id, 'status' => 'pending', 'requested_documents' => ['id_file' => 'id_files/draft.jpg']]);
        Storage::disk('public')->put('id_files/draft.jpg', 'old draft');
        $this->actingAs($user);
        DB::beginTransaction();
        $this->post(route('seller.account.documents'), ['id_file' => $this->image('id.jpg')])->assertRedirect();
        $new = $request->fresh()->requested_documents['id_file'];
        DB::rollBack();
        Storage::disk('public')->assertExists('id_files/draft.jpg');
        Storage::disk('public')->assertMissing($new);
        $this->assertSame('id_files/draft.jpg', $request->fresh()->requested_documents['id_file']);
        $this->post(route('seller.account.documents'), ['id_file' => $this->image('id.jpg')])->assertRedirect();
        Storage::disk('public')->assertMissing('id_files/draft.jpg');
        Storage::disk('public')->assertExists($request->fresh()->requested_documents['id_file']);
    }

    public function test_failed_storage_cleanup_is_logged_without_sensitive_details(): void
    {
        Log::spy();
        $disk = \Mockery::mock();
        $disk->shouldReceive('exists')->andReturn(false, true, false);
        $disk->shouldReceive('putFileAs')->once()->andThrow(new \RuntimeException('private customer details'));
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('messages')->andReturn($disk);
        DB::beginTransaction();
        try {
            app(MediaLifecycle::class)->store($this->image('private.jpg'), 'message_attachments', 'messages');
            $this->fail('Expected a storage failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('private customer details', $error->getMessage());
        }
        // Retry during rollback succeeds, even though the initial cleanup failed.
        DB::rollBack();
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $message === 'Media cleanup failed' && array_keys($context) === ['disk', 'path_hash', 'exception_type'] && ! str_contains(json_encode($context), 'private'))->atLeast()->once();
    }

    public function test_nested_rollback_cleans_child_uploads_and_keeps_parent_uploads_until_commit(): void
    {
        $media = app(MediaLifecycle::class);
        DB::beginTransaction();
        $parent = $media->store($this->image('parent.jpg'), 'message_attachments', 'messages');
        DB::beginTransaction();
        $child = $media->store($this->image('child.jpg'), 'message_attachments', 'messages');
        DB::rollBack();
        Storage::disk('messages')->assertMissing($child);
        Storage::disk('messages')->assertExists($parent);
        DB::commit();
        Storage::disk('messages')->assertExists($parent);
    }

    public function test_partial_attachment_batch_failure_rolls_back_new_messages_and_preserves_existing_attachment(): void
    {
        $sender = $this->seller();
        $sender->update(['account_type' => 'admin', 'is_admin' => true]);
        $receiver = $this->seller('receiver');
        $old = DB::table('messages')->insertGetId(['sender_id' => $sender->id, 'receiver_id' => $receiver->id, 'body' => 'Existing', 'attachment_path' => 'message_attachments/old.png']);
        Storage::disk('messages')->put('message_attachments/old.png', 'existing');
        DB::unprepared("CREATE TRIGGER reject_second_attachment BEFORE INSERT ON messages WHEN (SELECT COUNT(*) FROM messages) >= 2 BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        $this->actingAs($sender)->postJson(route('admin.messages.send'), ['receiver_id' => $receiver->id, 'attachments' => [$this->image('one.png'), $this->image('two.png')]])->assertStatus(500);
        $this->assertSame(1, DB::table('messages')->count());
        $this->assertTrue(DB::table('messages')->where('id', $old)->exists());
        $this->assertSame(['message_attachments/old.png'], Storage::disk('messages')->allFiles());
        $stranger = $this->seller('stranger');
        $this->actingAs($stranger)->get(route('message.media', ['path' => 'message_attachments/old.png']))->assertNotFound();
        Storage::disk('messages')->assertExists('message_attachments/old.png');
    }

    public function test_admin_document_approval_failure_keeps_user_and_request_and_existing_document(): void
    {
        $user = $this->seller();
        $user->update(['id_file' => 'id_files/current.png']);
        $admin = $this->seller('admin');
        $admin->update(['account_type' => 'admin', 'is_admin' => true]);
        foreach (['id_files/current.png', 'id_files/replacement.png'] as $path) {
            Storage::disk('public')->put($path, 'document');
        }
        $request = AccountUpdateRequest::create(['user_id' => $user->id, 'status' => 'pending', 'requested_documents' => ['id_file' => 'id_files/replacement.png']]);
        DB::unprepared("CREATE TRIGGER reject_notification BEFORE INSERT ON notifications BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        $this->actingAs($admin)->patchJson(route('admin.update-requests.approve', $request->id))->assertStatus(500);
        $this->assertSame('id_files/current.png', $user->fresh()->id_file);
        $this->assertSame('pending', $request->fresh()->status);
        Storage::disk('public')->assertExists(['id_files/current.png', 'id_files/replacement.png']);
        DB::unprepared('DROP TRIGGER reject_notification');
        $this->patch(route('admin.update-requests.approve', $request->id))->assertRedirect();
        $this->assertSame('id_files/replacement.png', $user->fresh()->id_file);
        Storage::disk('public')->assertMissing('id_files/current.png');
        Storage::disk('public')->assertExists('id_files/replacement.png');
    }

    public function test_shared_document_in_another_accounts_json_is_not_deleted_by_replacement(): void
    {
        $user = $this->seller();
        $other = $this->seller('other');
        foreach ([$user, $other] as $owner) {
            AccountUpdateRequest::create(['user_id' => $owner->id, 'status' => 'pending', 'requested_documents' => ['id_file' => 'id_files/shared.png']]);
        }
        Storage::disk('public')->put('id_files/shared.png', 'shared valid document');
        $this->actingAs($user)->post(route('seller.account.documents'), ['id_file' => $this->image('new.png'), 'user_id' => $other->id])->assertRedirect();
        Storage::disk('public')->assertExists('id_files/shared.png');
        $this->assertSame('id_files/shared.png', AccountUpdateRequest::where('user_id', $other->id)->firstOrFail()->requested_documents['id_file']);
    }

    public function test_committed_cleanup_failure_does_not_revert_successful_profile_update(): void
    {
        $user = $this->seller();
        $real = Storage::disk('profile_images');
        $real->put('avatars/old.jpg', 'old');
        Log::spy();
        $disk = \Mockery::mock();
        $disk->shouldReceive('exists')->andReturnUsing(fn ($path) => $real->exists($path));
        $disk->shouldReceive('putFileAs')->andReturnUsing(fn ($dir, $file, $name) => $real->putFileAs($dir, $file, $name));
        $disk->shouldReceive('delete')->with('avatars/old.jpg')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('profile_images')->andReturn($disk);
        $this->actingAs($user)->post(route('seller.account.shop-photo'), ['profile_picture' => $this->image('new.png')])->assertRedirect();
        $this->assertNotSame('avatars/old.jpg', $user->fresh()->profile_picture);
        $real->assertExists(['avatars/old.jpg', $user->fresh()->profile_picture]);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_failed_partial_storage_write_is_removed_without_overwriting_existing_file(): void
    {
        $real = Storage::disk('messages');
        $file = $this->image('partial.png');
        $existing = $file->hashName('message_attachments');
        $real->put($existing, 'existing valid bytes');
        $disk = \Mockery::mock();
        $disk->shouldReceive('exists')->andReturnUsing(fn ($path) => $real->exists($path));
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $upload, $name) use ($real) {
            $real->put($directory.'/'.$name, 'partial bytes');
            throw new \RuntimeException('Simulated write failure');
        });
        $disk->shouldReceive('delete')->once()->andReturnUsing(fn ($path) => $real->delete($path));
        Storage::shouldReceive('disk')->with('messages')->andReturn($disk);
        DB::beginTransaction();
        try {
            app(MediaLifecycle::class)->store($file, 'message_attachments', 'messages');
            $this->fail('Expected a failed write');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated write failure', $error->getMessage());
        } finally {
            DB::rollBack();
        }
        $this->assertSame([$existing], $real->allFiles());
        $this->assertSame('existing valid bytes', $real->get($existing));
    }
}
