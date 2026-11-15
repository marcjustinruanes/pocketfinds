<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class PublicImageController extends Controller
{
    public function show(Request $request, string $image, string $folder)
    {
        $path = $folder.'/'.$image;
        abort_unless(preg_match('~^(product_images|hero_banners|logistics_logos|profiles/shop-photos|profiles/shop-banners)/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|gif|avif)$~i', $path), 404);
        abort_unless(Schema::hasTable('public_image_assets'), 404);
        $asset = DB::table('public_image_assets')->where('path', $path)->first();
        abort_unless($asset && in_array($asset->mime, ['image/jpeg','image/png','image/webp','image/gif','image/avif'], true), 404);
        $bytes = base64_decode($asset->data_base64, true);
        abort_unless($bytes !== false && hash('sha256', $bytes) === $asset->sha256, 404);
        $response = response($bytes)->header('Content-Type', $asset->mime)->header('X-Content-Type-Options', 'nosniff')->header('Cache-Control', 'public, max-age=3600');
        $response->setEtag($asset->sha256);
        $response->isNotModified($request);
        return $response;
    }
}
