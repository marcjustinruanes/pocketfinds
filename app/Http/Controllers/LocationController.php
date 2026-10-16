<?php

namespace App\Http\Controllers;

class LocationController extends Controller
{
    /** Bundled snapshot of the existing PSGC provider; forms also work offline. */
    public function index(string $path)
    {
        $data = json_decode(file_get_contents(resource_path('data/philippine-locations.json')), true, flags: JSON_THROW_ON_ERROR);

        return response()->json($data[$path] ?? [])->header('Cache-Control', 'public, max-age=86400');
    }
}
