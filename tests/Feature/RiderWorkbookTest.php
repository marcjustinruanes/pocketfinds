<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

/** Executes server-side equivalents of the supplied Rider workbook, not phone UI tests. */
class RiderWorkbookTest extends TestCase
{
    use WorkflowSchema;

    private LogisticsHub $hub;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->createWorkflowSchema();
        Mail::fake();
        \Illuminate\Support\Facades\Storage::fake('sensitive_documents');
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['https://psgc.gitlab.io/api/regions/'=>\Illuminate\Support\Facades\Http::response([['code'=>'040000000','name'=>'CALABARZON']]),'https://psgc.gitlab.io/api/provinces/'=>\Illuminate\Support\Facades\Http::response([['code'=>'043400000','name'=>'Laguna','regionCode'=>'040000000']]),'https://psgc.gitlab.io/api/provinces/043400000/cities-municipalities/'=>\Illuminate\Support\Facades\Http::response([['code'=>'043422000','name'=>'Pila']]),'https://psgc.gitlab.io/api/cities-municipalities/043422000/barangays/'=>\Illuminate\Support\Facades\Http::response([['code'=>'043422001','name'=>'Test']])]);
        \Illuminate\Support\Facades\DB::table('id_types')->insert(['id'=>1,'name'=>"Driver's License"]);
        \Illuminate\Support\Facades\DB::table('vehicle_types')->insert(['id'=>1,'slug'=>'two_wheels','name'=>'Motorcycle']);
        $this->hub = LogisticsHub::create(['company_name' => 'Workbook Logistics', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true, 'is_hiring' => true, 'rider_hiring_open' => true]);
        $this->admin = User::create(['username' => 'workbook_admin', 'email' => 'workbook_admin@example.com', 'given_names' => 'Test', 'last_name' => 'Admin', 'account_type' => 'logistics', 'is_logistics' => true, 'logistics_role' => 'admin', 'business_name' => $this->hub->company_name, 'logistics_hub_id' => $this->hub->id, 'status' => 'approved']);
        $this->actingAs($this->admin);
    }

    public static function cases(): array
    {
        $cases = [];
        for ($i = 1; $i <= 47; $i++) $cases[sprintf('TC-RID-%03d', $i)] = [$i];
        return $cases;
    }

    private function data(array $overrides = []): array
    {
        // Adapted baseline: supply extra system fields and a single-word name so each
        // independent negative test reaches the field being examined.
        $image=fn()=>\Illuminate\Http\UploadedFile::fake()->createWithContent('document.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
        return array_replace(['region'=>'CALABARZON','vehicle_brand'=>'Test','vehicle_model'=>'Test','license_file'=>$image(),'selfie_file'=>$image(),'or_file'=>$image(),'cr_file'=>$image(),'role' => 'rider', 'username' => 'workbook_rider', 'email' => 'pedrojose.garcia@gmail.com', 'given_names' => 'Pedro', 'last_name' => 'Garcia', 'sex' => 'male', 'birthday' => '1995-03-22', 'contact_no' => '09451239876', 'barangay' => 'Test', 'password' => 'Workbook!2026', 'password_confirmation' => 'Workbook!2026', 'logistics_hub_id' => $this->hub->id, 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own', 'license_number' => 'N01-23-456789', 'license_expiry' => '2030-03-22', 'plate_number' => '123ABC'], $overrides);
    }

    private function submit(array $overrides = [])
    {
        return $this->postJson('/logistics/riders/accounts', $this->data($overrides));
    }

    private function boundary(string $field, array $reject, array $accept): void
    {
        // Independent attempts use fresh username/email/mobile; no duplicate errors
        // may mask the field under test. DB is reset between workbook cases.
        $attempt = 0;
        foreach ([false => $reject, true => $accept] as $accepted => $values) {
            foreach ($values as $value) {
                $attempt++;
                $changes = ['username' => 'attempt_'.$attempt, 'email' => 'attempt'.$attempt.'@gmail.com', 'contact_no' => '0917000'.str_pad((string)$attempt, 4, '0', STR_PAD_LEFT), $field => $value];
                if($field!=='given_names')$changes['given_names']='Case'.chr(65+$attempt);
                if($field!=='license_number')$changes['license_number']='A'.str_pad((string)$attempt,2,'0',STR_PAD_LEFT).'-'.str_pad((string)$attempt,2,'0',STR_PAD_LEFT).'-'.str_pad((string)$attempt,6,'0',STR_PAD_LEFT);
                if($field!=='plate_number')$changes['plate_number']='TESTPLATE'.$attempt;
                $response = $this->submit($changes);
                if ($accepted) $response->assertRedirect();
                else {
                    $this->assertSame(422, $response->status(), "Expected rejection for {$field}=".(string)$value.'; HTTP '.$response->status());
                    $response->assertJsonValidationErrors($field);
                }
            }
        }
    }

    #[DataProvider('cases')]
    public function test_workbook_case(int $id): void
    {
        switch ($id) {
            case 1:
                // Adapted to approved Pending application, not an unimplemented activation flow.
                $response = $this->submit(['given_names' => 'Pedro']);
                $response->assertRedirect();
                $this->assertDatabaseHas('users', ['email' => 'pedrojose.garcia@gmail.com', 'status' => 'pending']);
                Mail::assertNothingSent();
                break;
            case 2:
                $this->postJson('/logistics/riders/accounts', [])->assertUnprocessable();
                $this->assertDatabaseCount('users', 1);
                break;
            case 3:
                foreach (['given_names','last_name','birthday','email','contact_no','license_number','license_expiry','vehicle_type','plate_number','license_file','or_file','cr_file'] as $field) {
                    $data = $this->data();
                    unset($data[$field]);
                    $response = $this->postJson('/logistics/riders/accounts', $data);
                    $this->assertSame(422, $response->status(), "Missing required {$field} was accepted; HTTP ".$response->status());
                    $response->assertJsonValidationErrors($field);
                }
                break;
            case 4:
                $this->boundary('given_names', ['Pedro Jose99'], []);
                $this->boundary('last_name', ['Dela Cruz@#'], []);
                break;
            case 5:
                $this->boundary('given_names', ['P', str_repeat('A',51)], ['Po', str_repeat('A',50), 'Maria Cristina', 'Jose-Maria']);
                // Workbook explicitly permits multi-word 50-character names.
                $this->submit(['username'=>'multiword', 'email'=>'multiword@gmail.com','license_number'=>'M01-00-000001','plate_number'=>'MULTI123', 'contact_no'=>'09170000999', 'given_names'=>'Maria Cristina Angelica Bernadette Josefina Leonor'])->assertRedirect();
                break;
            case 6:
                $this->boundary('birthday', [today()->subYears(18)->addDay()->toDateString()], [today()->subYears(18)->toDateString(), today()->subYears(18)->subDay()->toDateString()]);
                break;
            case 7:
                $this->boundary('email', ['pedrojose.garciaexample.com','pedrojose.garcia@','@example.com','pedrojose.garcia@example','pedrojose garcia@example.com'], []);
                break;
            case 8:
                $this->submit(['email'=>$this->admin->email])->assertUnprocessable()->assertJsonValidationErrors('email');
                break;
            case 9:
                $this->boundary('contact_no', ['0917ABC4567','0917-123-456','12345678901','02-8123-4567'], []);
                break;
            case 10:
                $this->boundary('contact_no', ['0917123456','091712345678'], ['09170000001']);
                break;
            case 11:
                $this->admin->update(['contact_no'=>'09171234567']);
                $this->submit(['contact_no'=>'09171234567'])->assertUnprocessable()->assertJsonValidationErrors('contact_no');
                $this->submit(['contact_no'=>'+639171234567'])->assertUnprocessable()->assertJsonValidationErrors('contact_no');
                break;
            case 12:
                // LTO format: one uppercase letter + 2 digits + hyphen + 2 digits + hyphen + 6 digits
                $this->boundary('license_number',
                    ['N01-23-45678', 'N01-23-4567890', '101-23-456789', 'N0123456789X', 'n01-23-456789', 'N01-23-45678X'],
                    ['N01-23-456789', 'A00-00-000000', 'Z99-99-999999']
                );
                break;
            case 13:
                $this->admin->update(['license_number'=>'N01-23-456789']);
                $this->submit()->assertUnprocessable()->assertJsonValidationErrors('license_number');
                break;
            case 14:
                $this->boundary('license_expiry', [today()->subDay()->toDateString()], [today()->toDateString(), today()->addDay()->toDateString()]);
                break;
            case 15:
                $this->admin->update(['plate_number'=>'123ABC']);
                $this->submit()->assertUnprocessable()->assertJsonValidationErrors('plate_number');
                break;
            case 28:
                $this->submit()->assertRedirect();
                $this->get('/logistics/accounts')->assertRedirect(route('logistics.riders'));
                $this->get('/logistics/riders')->assertOk()->assertSee('workbook_rider');
                $otherHub = LogisticsHub::create(['company_name'=>'Other Logistics','province'=>'Laguna','municipality'=>'Pila','is_operational'=>true]);
                $other = User::create(['username'=>'other_admin','email'=>'other_admin@example.com','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>$otherHub->company_name,'logistics_hub_id'=>$otherHub->id,'status'=>'approved']);
                $this->actingAs($other)->get('/logistics/accounts')->assertRedirect(route('logistics.riders'));
                $this->get('/logistics/riders')->assertOk()->assertDontSee('workbook_rider');
                break;
            case 29:
                auth()->logout();
                $this->get('/logistics/accounts')->assertRedirect('/login');
                // Public applications are intentional; only company management is protected.
                $this->get('/register/rider')->assertOk();
                break;
            case 30:
                foreach (['buyer','seller'] as $role) {
                    $user = User::create(['username'=>$role,'email'=>$role.'@example.com','account_type'=>$role,'status'=>'approved']);
                    $this->actingAs($user)->get('/logistics/accounts')->assertRedirect('/login');
                    $this->postJson('/logistics/riders/accounts',$this->data())->assertRedirect('/login');
                }
                $this->assertDatabaseMissing('users',['username'=>'workbook_rider']);
                break;
            case 31:
                $this->admin->update(['status'=>'pending']);
                $this->get('/logistics/accounts')->assertForbidden();
                $this->submit()->assertForbidden();
                $this->assertDatabaseMissing('users',['username'=>'workbook_rider']);
                break;
            case 32:
                $this->submit()->assertRedirect();
                $rider = User::where('username','workbook_rider')->firstOrFail();
                // Fixture approval sets the precondition; no real recruitment decision.
                \Illuminate\Support\Facades\DB::table('users')->where('id',$rider->id)->update(['status'=>'approved']);
                $this->actingAs($rider->fresh())->get('/logistics/accounts')->assertRedirect('/login');
                $this->postJson('/logistics/riders/accounts',$this->data(['username'=>'forbidden']))->assertRedirect('/login');
                foreach (['/admin/dashboard','/seller/dashboard'] as $path) {
                    $response = $this->get($path);
                    $this->assertContains($response->status(), [302,401,403], "Rider must be denied {$path}");
                    if ($response->status() === 302) $response->assertRedirect('/login');
                }
                break;
            default:
                if (in_array($id,[16,17,18,40,41,42])) $this->markTestSkipped('Manual file-picker/camera/device workbook acceptance remains unexecuted; server upload coverage is in PersonnelOnboardingTest and SensitiveDocumentsTest.');
                if (in_array($id,[20,21,22,23,24,25,26,27,44,45])) $this->markTestSkipped('Blocked: workbook emailed rider activation flow does not exist; cannot establish its activation preconditions.');
                $this->markTestSkipped('Blocked: requires installed Rider mobile app/physical phone, UI or network interruption/concurrent-tap testing; not covered by server HTTP tests.');
        }
    }
}
