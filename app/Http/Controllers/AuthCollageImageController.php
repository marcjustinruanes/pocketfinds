<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthCollageImageController extends Controller
{
    public function __invoke(Request $request, string $slot = 'main')
    {
        $prefix = \App\Support\AuthCollage::prefix($slot);
        $image = DB::table('settings')->where('id', 1)->first([
            $prefix.'data', $prefix.'mime', $prefix.'sha256',
        ]);
        abort_unless($image && $image->{$prefix.'data'} && in_array($image->{$prefix.'mime'}, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $response = response($image->{$prefix.'data'}, 200, [
            'Content-Type' => $image->{$prefix.'mime'},
            'Cache-Control' => 'public, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ])->setEtag($image->{$prefix.'sha256'});
        $response->isNotModified($request);
        return $response;
    }
}
