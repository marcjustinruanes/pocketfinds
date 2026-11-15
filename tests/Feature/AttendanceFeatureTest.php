<?php
namespace Tests\Feature;
use App\Http\Middleware\AttendanceFeature;
use Illuminate\Http\Request;
use Tests\TestCase;
class AttendanceFeatureTest extends TestCase
{
    public function test_disabled_attendance_blocks_pages_exports_and_writes(): void
    {
        config(['features.attendance'=>false]);
        foreach (['/logistics/attendance','/logistics/attendance/export','/logistics/attendance/records/example','/logistics/my-attendance','/logistics/my-attendance/records/example'] as $url) $this->get($url)->assertNotFound();
        foreach (['/logistics/attendance/marks','/logistics/attendance/corrections/example','/logistics/attendance/records/example','/logistics/my-attendance/clock/in','/logistics/my-attendance/clock/out','/logistics/my-attendance/corrections'] as $url) $this->post($url,[])->assertNotFound();
    }
    public function test_enabling_attendance_restores_normal_request_processing(): void
    {
        config(['features.attendance'=>true]);
        $result=(new AttendanceFeature)->handle(Request::create('/logistics/my-attendance'),fn()=>response('enabled'));
        $this->assertSame('enabled',$result->getContent());
        config(['features.attendance'=>false]);
        $result=(new AttendanceFeature)->handle(Request::create('/logistics/riders'),fn()=>response('team'));
        $this->assertSame('team',$result->getContent());
    }
}
