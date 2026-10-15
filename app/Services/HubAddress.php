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
        if (! in_array($level, ['provinces', 'municipalities', 'barangays'], true)
            || ($level !== 'provinces' && ! preg_match('/^[0-9]{9}$/D', $code ?? ''))) {
            throw ValidationException::withMessages(['address' => 'Choose a valid address location.']);
        }
        $path = match ($level) {
            'provinces' => 'provinces/',
            'municipalities' => 'provinces/'.$code.'/cities-municipalities/',
            'barangays' => 'cities-municipalities/'.$code.'/barangays/',
        };
        try {
            return Cache::remember('hub-address:'.$path, now()->addHours(6), function () use ($path) {
                $rows = Http::acceptJson()->connectTimeout(3)->timeout(8)->get('https://psgc.gitlab.io/api/'.$path)->throw()->json();
                if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
                    throw new \RuntimeException('Invalid location response.');
                }

                return array_map(function ($row) {
                    if (! is_array($row) || ! preg_match('/^[0-9]{9}$/D', $row['code'] ?? '') || ! is_string($row['name'] ?? null)) {
                        throw new \RuntimeException('Invalid location entry.');
                    }

                    return ['code' => $row['code'], 'name' => $row['name']];
                }, $rows);
            });
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['address' => 'Address choices could not be loaded. Please retry shortly.']);
        }
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
