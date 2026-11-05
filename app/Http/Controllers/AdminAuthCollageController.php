<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\AuthCollage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAuthCollageController extends Controller
{
    public function update(Request $request, string $slot)
    {
        abort_unless($request->user()?->is_admin, 403);
        $prefix = AuthCollage::prefix($slot);
        $validator = validator($request->all(), [
            'collage_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'crop_x' => ['required', 'integer', 'between:0,100'],
            'crop_y' => ['required', 'integer', 'between:0,100'],
            'crop_zoom' => ['required', 'integer', 'between:100,200'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('admin.settings')->withErrors($validator)->withInput()
                ->with('settings_tab', 'collage')->with('collage_slot', $slot);
        }
        $data = $validator->validated();
        $changes = ['updated_by' => $request->user()->id, 'updated_at' => now()];
        if ($request->hasFile('collage_image')) {
            $file = $request->file('collage_image');
            $bytes = file_get_contents($file->getRealPath());
            $changes += [$prefix.'data' => $bytes, $prefix.'mime' => $file->getMimeType(), $prefix.'sha256' => hash('sha256', $bytes)];
        }
        $setting = Setting::current();
        DB::transaction(function () use ($setting, $slot, $data, $changes) {
            $row = DB::table('settings')->where('id', $setting->id)->lockForUpdate()->first(['auth_collage_crops']);
            $crops = json_decode($row->auth_collage_crops ?? '{}', true) ?: [];
            $crops[$slot] = ['x' => (int) $data['crop_x'], 'y' => (int) $data['crop_y'], 'zoom' => (int) $data['crop_zoom']];
            DB::table('settings')->where('id', $setting->id)->update($changes + ['auth_collage_crops' => json_encode($crops)]);
        });
        return redirect()->route('admin.settings')->with('settings_tab', 'collage')
            ->with('success', AuthCollage::SLOTS[$slot].' saved.');
    }
}
