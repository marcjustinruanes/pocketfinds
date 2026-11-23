<?php

namespace App\Http\Controllers;

class AppDownloadController extends Controller
{
    public function __invoke()
    {
        $path = storage_path('app/releases/pocketfinds.apk');
        abort_unless(is_file($path), 404, 'The Android download is not available yet.');

        return response()->download($path, 'PocketFinds.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'public, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
