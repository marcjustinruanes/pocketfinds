<?php

namespace App\Services;

use App\Models\LogisticsHub;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class HubAddress
{
    public function locations(string $level, ?string $code = null): array
    {
        if (! in_array($level, ['regions', 'provinces', 'municipalities', 'regional-municipalities', 'barangays'], true)
            || (in_array($level, ['regions', 'provinces'], true) ? $code !== null : ! preg_match('/^[0-9]{9}$/D', $code ?? ''))) {
            throw ValidationException::withMessages(['address' => 'Choose a valid address location.']);
        }
        $path = match ($level) {
            'regions' => 'regions/',
            'provinces' => 'provinces/',
            'regional-municipalities' => 'regions/'.$code.'/cities-municipalities/',
            'municipalities' => 'provinces/'.$code.'/cities-municipalities/',
            'barangays' => 'cities-municipalities/'.$code.'/barangays/',
        };
        try {
            return Cache::remember('hub-address:v2:'.$path, now()->addHours(6), function () use ($path) {
                $rows = Http::acceptJson()->connectTimeout(3)->timeout(8)->withOptions(['allow_redirects' => false])->get('https://psgc.gitlab.io/api/'.$path)->throw()->json();
                if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
                    throw new \RuntimeException('Invalid location response.');
                }

                return array_map(function ($row) {
                    if (! is_array($row) || ! is_string($row['code'] ?? null) || ! preg_match('/^[0-9]{9}$/D', $row['code']) || ! is_string($row['name'] ?? null) || trim($row['name']) === '' || mb_strlen($row['name']) > 255) {
                        throw new \RuntimeException('Invalid location entry.');
                    }

                    $result = ['code' => $row['code'], 'name' => trim($row['name'])];
                    if (isset($row['regionCode'])) {
                        if (! is_string($row['regionCode']) || ! preg_match('/^[0-9]{9}$/D', $row['regionCode'])) {
                            throw new \RuntimeException('Invalid region reference.');
                        }
                        $result['regionCode'] = $row['regionCode'];
                    }
                    return $result;
                }, $rows);
            });
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['address' => 'Address choices could not be loaded. Please retry shortly.']);
        }
    }

    public function validateAccount(array $data): array
    {
        $find = fn (array $rows, string $name) => collect($rows)->first(fn ($row) => strcasecmp($row['name'], trim($name)) === 0);
        $region = $find($this->locations('regions'), $data['region']);
        if (! $region) {
            throw ValidationException::withMessages(['region' => 'Select a valid region.']);
        }
        $provinces = array_values(array_filter($this->locations('provinces'), fn ($row) => ($row['regionCode'] ?? null) === $region['code']));
        $province = $find($provinces, $data['province']);
        // NCR has no provinces; the existing form uses its region name in that field.
        $regionOnly = $provinces === [] && strcasecmp(trim($data['province']), $region['name']) === 0;
        $cities = $province ? $this->locations('municipalities', $province['code']) : ($regionOnly ? $this->locations('regional-municipalities', $region['code']) : []);
        $city = $find($cities, $data['municipality']);
        $barangay = $city ? $find($this->locations('barangays', $city['code']), $data['barangay']) : null;
        if (! $city || ! $barangay) {
            throw ValidationException::withMessages(['address' => 'Select a province, municipality and barangay belonging to the selected region.']);
        }

        return ['region' => $region['name'], 'province' => $province['name'] ?? $region['name'], 'municipality' => $city['name'], 'barangay' => $barangay['name']];
    }

    public function validateBarangay(LogisticsHub $hub, string $name): string
    {
        $find = fn (array $rows, string $name) => collect($rows)->first(fn ($row) => strcasecmp($row['name'], trim($name)) === 0);
        $province = $find($this->locations('provinces'), $hub->province);
        $city = $province ? $find($this->locations('municipalities', $province['code']), $hub->municipality) : null;
        $barangay = $city ? $find($this->locations('barangays', $city['code']), $name) : null;
        if (! $barangay) {
            throw ValidationException::withMessages(['barangay' => 'Select a barangay within the assigned hub municipality.']);
        }

        return $barangay['name'];
    }

    public function validate(array $data, ?LogisticsHub $hub = null): array
    {
        $province = collect($this->locations('provinces'))->firstWhere('code', $data['province_code']);
        $municipality = $province ? collect($this->locations('municipalities', $province['code']))->firstWhere('code', $data['municipality_code']) : null;
        $barangay = $municipality ? collect($this->locations('barangays', $municipality['code']))->firstWhere('code', $data['barangay_code']) : null;
        if (! $province || ! $municipality || ! $barangay) {
            throw ValidationException::withMessages(['address' => 'Select a barangay belonging to the chosen municipality and province.']);
        }
        if ($hub && (strcasecmp(trim($hub->province), $province['name']) !== 0 || strcasecmp(trim($hub->municipality), $municipality['name']) !== 0)) {
            throw ValidationException::withMessages(['address' => 'This address must remain within the hub municipality and province.']);
        }

        return ['province' => $province['name'], 'municipality' => $municipality['name'],
            'name' => trim($data['name']), 'contact_no' => $data['contact_no'],
            'address' => implode(', ', [trim($data['address_line']), $barangay['name'], $municipality['name'], $province['name']])];
    }
}
