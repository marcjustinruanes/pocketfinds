<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RiderBarangayCoverage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RiderCoverageController extends LogisticsController
{
    private function rider(string $id): User
    {
        return User::with('logisticsHub')->where('account_type', 'rider')->where('business_name', auth()->user()->business_name)->findOrFail($id);
    }

    public function edit(string $id, RiderBarangayCoverage $coverage)
    {
        abort_unless($coverage->installed(), 503, 'Install the barangay route migration first.');
        $rider = $this->rider($id);
        $choices = [];
        $coverageError = null;
        if (! $rider->logisticsHub || ! $rider->logisticsHub->is_operational || $rider->logisticsHub->company_name !== $rider->business_name) {
            $coverageError = 'Assign the rider to an operational hub in your company before managing barangay coverage.';
        } else {
            try {
                $choices = $coverage->choices($rider->logisticsHub);
            } catch (ValidationException $e) {
                $coverageError = collect($e->errors())->flatten()->first();
            }
        }
        $selected = $rider->barangayRoutes()->where('hub_id', $rider->logistics_hub_id)->pluck('barangay_code')->all();
        $version = $coverage->version($rider);

        return view('logistics.rider-coverage', array_merge($this->sidebarCounts(), compact('rider', 'choices', 'coverageError', 'selected', 'version')));
    }

    public function update(Request $request, string $id, RiderBarangayCoverage $coverage)
    {
        $rider = $this->rider($id);
        $request->merge(['barangays' => $request->input('barangays', [])]);
        $data = $request->validate(['hub_id' => 'required|integer', 'version' => 'required|string|size:64',
            'barangays' => 'present|array|max:1000', 'barangays.*' => ['required', 'string', 'distinct', 'regex:/^[0-9]{9}$/D']]);
        $coverage->save(auth()->user(), $rider, $data['barangays'], (int) $data['hub_id'], $data['version']);

        return back()->with('success', 'Barangay coverage updated.');
    }
}
