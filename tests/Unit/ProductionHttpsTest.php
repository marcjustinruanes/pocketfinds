<?php

namespace Tests\Unit;

use App\Http\Middleware\ProductionHttps;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductionHttpsTest extends TestCase
{
    public function test_production_redirect_preserves_method_path_and_query_without_using_untrusted_host(): void
    {
        $middleware = new ProductionHttps(new Repository(['app'=>['env'=>'production','url'=>'https://pocketfindsph.shop']]));
        $response = $middleware->handle(Request::create('http://pocketfindsph.shop/commissions?from=2026-11-07','POST'), function () {
            $this->fail('An insecure production request reached the application');
        });
        $this->assertSame(308,$response->getStatusCode());
        $this->assertSame('https://pocketfindsph.shop/commissions?from=2026-11-07',$response->headers->get('Location'));
    }

    public function test_secure_production_response_gets_hsts(): void
    {
        $middleware = new ProductionHttps(new Repository(['app'=>['env'=>'production','url'=>'https://pocketfindsph.shop']]));
        $response = $middleware->handle(Request::create('https://pocketfindsph.shop/commissions'),fn()=>new Response('protected',401));
        $this->assertSame(401,$response->getStatusCode());
        $this->assertSame('max-age=31536000',$response->headers->get('Strict-Transport-Security'));
    }

    public function test_testing_localhost_and_other_hosts_are_not_redirected_or_given_hsts(): void
    {
        foreach([['testing','http://pocketfindsph.shop/'],['production','http://127.0.0.1:8000/'],['production','https://other.example/']] as [$env,$url]) {
            $middleware = new ProductionHttps(new Repository(['app'=>['env'=>$env,'url'=>'https://pocketfindsph.shop']]));
            $response = $middleware->handle(Request::create($url),fn()=>new Response('ok'));
            $this->assertSame(200,$response->getStatusCode());
            $this->assertFalse($response->headers->has('Strict-Transport-Security'));
        }
    }
}
