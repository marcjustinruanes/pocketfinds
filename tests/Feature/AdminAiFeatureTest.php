<?php
namespace Tests\Feature;
use App\Http\Middleware\AdminAiFeature;
use Illuminate\Http\Request;
use Tests\TestCase;
class AdminAiFeatureTest extends TestCase
{
    public function test_disabled_ai_blocks_pages_and_actions(): void
    {
        config(['features.admin_ai'=>false]);
        foreach (['/admin/ai','/admin/ai/export','/admin/ai/orders/1','/admin/ai/products/1/photo'] as $url) $this->get($url)->assertNotFound();
        foreach (['/admin/ai/analyze','/admin/ai/prepare-draft'] as $url) $this->post($url,[])->assertNotFound();
        $this->delete('/admin/ai/chat')->assertNotFound();
        $html=view('admin.partials.ai-link',['aiMode'=>'product','aiLabel'=>'AI screen'])->render();
        $this->assertStringNotContainsString('AI screen',$html);
    }
    public function test_feature_can_be_restored_without_affecting_other_pages(): void
    {
        config(['features.admin_ai'=>true]);
        $response=(new AdminAiFeature)->handle(Request::create('/admin/ai'),fn()=>response('enabled'));
        $this->assertSame('enabled',$response->getContent());
        config(['features.admin_ai'=>false]);
        $response=(new AdminAiFeature)->handle(Request::create('/admin/products'),fn()=>response('products'));
        $this->assertSame('products',$response->getContent());
    }
}
