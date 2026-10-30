<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\LogisticsHub;
use App\Services\SensitiveDocuments;
use App\Services\MediaLifecycle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class SensitiveDocumentsTest extends TestCase
{
    use WorkflowSchema;
    private User $owner;
    private User $admin;
    private string $path = 'license_files/fixture.pdf';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        Storage::fake('public'); Storage::fake('sensitive_documents');
        $hub = LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Santa Cruz','province'=>'Laguna','is_operational'=>true]);
        $this->owner = User::create(['username'=>'owner','account_type'=>'rider','status'=>'approved','business_name'=>'Alpha','logistics_hub_id'=>$hub->id,'license_file'=>$this->path]);
        $this->admin = User::create(['username'=>'admin','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','status'=>'approved','business_name'=>'Alpha']);
        Storage::disk('sensitive_documents')->put($this->path, "%PDF-1.4\nPrivate fixture");
    }

    private function url(): string { return app(SensitiveDocuments::class)->url($this->path); }

    public function test_document_is_private_and_scoped_even_with_a_valid_copied_url(): void
    {
        $url=$this->url(); $this->assertStringNotContainsString('license_files', $url);
        $this->get($url)->assertRedirect('/login');
        $response=$this->actingAs($this->admin)->get($url)->assertOk();
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('Content-Security-Policy',"sandbox; default-src 'none'");
        foreach ([['rider','Alpha',null],['logistics','Alpha','hub_staff'],['logistics','Other','admin'],['buyer','Alpha',null],['seller','Alpha',null]] as $i=>[$type,$company,$role]) {
            $other=User::create(['username'=>'other'.$i,'account_type'=>$type,'business_name'=>$company,'logistics_role'=>$role,'is_logistics'=>$type==='logistics','status'=>'approved','logistics_hub_id'=>$this->owner->logistics_hub_id]);
            $this->actingAs($other)->get($url)->assertNotFound();
        }
        $this->actingAs($this->owner)->get($url)->assertOk();
        $platform=User::create(['username'=>'platform','account_type'=>'admin','is_admin'=>true,'status'=>'approved']);
        $this->actingAs($platform)->get($url)->assertOk();
        DB::table('users')->where('id',$this->admin->id)->update(['status'=>'suspended']);
        $this->actingAs($this->admin)->get($url)->assertNotFound();
        $this->actingAs($this->owner)->get(route('sensitive-documents.show','tampered'))->assertNotFound();
        $this->get(route('sensitive-documents.show',Crypt::encryptString('../.env')))->assertNotFound();
    }

    public function test_sensitive_upload_and_rollback_never_write_public_files(): void
    {
        $path=null;
        try {
            DB::transaction(function () use (&$path) {
                $path=app(MediaLifecycle::class)->store(UploadedFile::fake()->createWithContent('id.pdf',"%PDF-1.4\nNew identity"),'id_files','public');
                Storage::disk('sensitive_documents')->assertExists($path);
                Storage::disk('public')->assertMissing($path);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $e) { $this->assertSame('rollback',$e->getMessage()); }
        Storage::disk('sensitive_documents')->assertMissing($path);
    }

    public function test_legacy_migration_is_verified_idempotent_and_refuses_conflicting_copies(): void
    {
        Storage::disk('sensitive_documents')->delete($this->path);
        $bytes="%PDF-1.4\nLegacy identity";
        Storage::disk('public')->put($this->path,$bytes);
        $this->actingAs($this->admin)->get($this->url())->assertOk();
        $this->artisan('documents:privatize')->assertSuccessful();
        Storage::disk('public')->assertExists($this->path);
        $this->artisan('documents:privatize',['--apply'=>true])->assertSuccessful();
        Storage::disk('public')->assertMissing($this->path);
        $this->assertSame($bytes,Storage::disk('sensitive_documents')->get($this->path));
        $this->artisan('documents:privatize',['--apply'=>true])->assertSuccessful();
        $this->assertDatabaseHas('users',['id'=>$this->owner->id,'license_file'=>$this->path]);
        Storage::disk('public')->put($this->path,'conflicting contents');
        $this->artisan('documents:privatize',['--apply'=>true])->assertExitCode(1);
        Storage::disk('public')->assertExists($this->path);
        $this->assertSame($bytes,Storage::disk('sensitive_documents')->get($this->path));
    }
    public function test_review_component_uses_an_opaque_authenticated_document_url(): void
    {
        $html=\Illuminate\Support\Facades\Blade::render('<x-admin-doc-thumb :path="$path" label="Driver licence" />',['path'=>$this->path]);
        $this->assertStringContainsString('/private-documents/',$html);
        $this->assertStringNotContainsString($this->path,$html);
        $this->assertStringNotContainsString('/storage/',$html);
        $this->assertArrayNotHasKey('license_file', $this->owner->toArray());
    }
}
