<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\{RedirectResponse, Response};

class ProductionHttps
{
    public function __construct(private Repository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $url = parse_url((string) $this->config->get('app.url'));
        $productionHost = $this->config->get('app.env') === 'production'
            && ($url['scheme'] ?? '') === 'https'
            && strtolower($url['host'] ?? '') === strtolower($request->getHost());

        if ($productionHost && !$request->isSecure()) {
            $target = 'https://'.$url['host'].(isset($url['port']) ? ':'.$url['port'] : '').$request->getRequestUri();
            return new RedirectResponse($target, 308);
        }

        $response = $next($request);
        if ($productionHost && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        return $response;
    }
}
