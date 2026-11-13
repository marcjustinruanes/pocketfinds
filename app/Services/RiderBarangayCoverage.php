<?php

namespace App\Services;

use App\Models\LogisticsHub;
use App\Models\RiderBarangayRoute;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RiderBarangayCoverage
{
    public function installed(): bool
    {
        return Schema::hasTable('rider_barangay_routes');
    }

    public function key(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    public function barangay(Shipment $shipment, string $leg): string
    {
        return trim((string) ($leg === 'pickup' ? $shipment->order?->seller?->barangay : ($shipment->order?->shipping_address['barangay'] ?? '')));
    }

    public function choices(LogisticsHub $hub): array
    {
        $address = app(HubAddress::class);
        $find = fn ($rows, $name) => collect($rows)->first(fn ($row) => $this->key($row['name']) === $this->key((string) $name));
        $province = $find($address->locations('provinces'), $hub->province);
        if ($province) {
            $cities = $address->locations('municipalities', $province['code']);
        } else {
            // NCR hubs use the region name instead of a province.
            $region = $find($address->locations('regions'), $hub->region ?: $hub->province);
            $cities = $region && $this->key($region['name']) === $this->key($hub->province)
                ? $address->locations('regional-municipalities', $region['code']) : [];
        }
        $city = $find($cities, $hub->municipality);
        if (! $city) {
            throw ValidationException::withMessages(['barangays' => 'The assigned hub municipality could not be matched. Review its address before assigning routes.']);
        }

        return $address->locations('barangays', $city['code']);
    }

    public function version(User $rider): string
    {
        return hash('sha256', json_encode([$rider->logistics_hub_id, $rider->business_name,
            RiderBarangayRoute::where('rider_id', $rider->id)->orderBy('hub_id')->orderBy('barangay_code')->lockForUpdate()->get(['hub_id', 'barangay_code'])->toArray()]));
    }

    public function save(User $actor, User $rider, array $codes, int $hubId, string $version): void
    {
        abort_unless($this->installed(), 503, 'Install the barangay route migration first.');
        $hub = LogisticsHub::findOrFail($hubId);
        abort_unless($rider->business_name === $actor->business_name && $hub->company_name === $actor->business_name, 404);
        abort_unless((int) $rider->logistics_hub_id === $hubId, 409, 'The rider hub changed. Reload the coverage form.');
        $choices = collect($this->choices($hub))->keyBy('code');
        foreach ($codes as $code) {
            if (! $choices->has($code)) {
                throw ValidationException::withMessages(['barangays' => 'Choose barangays belonging to the rider assigned hub.']);
            }
        }
        $location = $hub->only(['company_name', 'province', 'municipality', 'region']);
        DB::transaction(function () use ($actor, $rider, $codes, $hubId, $version, $choices, $location) {
            // Match personnel/assignment locking: hubs before users, then coverage rows.
            $hub = LogisticsHub::whereKey($hubId)->lockForUpdate()->firstOrFail();
            abort_unless($hub->only(array_keys($location)) === $location, 409, 'Hub geography changed. Reload before saving.');
            $users = User::whereIn('id', [$actor->id, $rider->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $admin = $users[$actor->id];
            $current = $users[$rider->id];
            abort_unless($admin->account_type === 'logistics' && ! $admin->is_admin && $admin->isLogisticsAdmin() && $admin->status === 'approved', 403);
            abort_unless($current->account_type === 'rider' && $current->business_name === $admin->business_name && $hub->company_name === $admin->business_name, 404);
            abort_unless($hub->is_operational && (int) $current->logistics_hub_id === $hubId, 409, 'The rider hub changed or is no longer operational. Reload the form.');
            RiderBarangayRoute::where('rider_id', $current->id)->orderBy('id')->lockForUpdate()->get();
            abort_unless(hash_equals($this->version($current), $version), 409, 'Coverage changed. Reload before saving.');
            RiderBarangayRoute::where('rider_id', $current->id)->delete();
            foreach ($codes as $code) {
                RiderBarangayRoute::create(['rider_id' => $current->id, 'hub_id' => $hubId, 'barangay_code' => $code,
                    'barangay_name' => $choices[$code]['name'], 'barangay_key' => $this->key($choices[$code]['name'])]);
            }
        }, 3);
    }
}
