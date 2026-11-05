<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RegistrationPostalCode
{
    public function lookup(?string $code, ?string $municipality): ?string
    {
        if (!preg_match('/^\d{9,10}$/D', (string) $code) || !$municipality) return null;
        $code = strlen($code) === 9 ? substr($code, 0, 2).'0'.substr($code, 2) : $code;
        try {
            $locations = Cache::remember('registration_postal_api_v1', now()->addDay(), function () {
                $cities = Http::acceptJson()->timeout(5)->get('https://psgc.cloud/api/cities')->throw()->json();
                $municipalities = Http::acceptJson()->timeout(5)->get('https://psgc.cloud/api/municipalities')->throw()->json();
                if (!is_array($cities) || !is_array($municipalities)) throw new \RuntimeException('Invalid postal response.');
                return array_merge($cities, $municipalities);
            });
            foreach ($locations as $location) {
                if ((string) ($location['code'] ?? '') !== $code) continue;
                if ($this->name((string) ($location['name'] ?? '')) !== $this->name($municipality)) return null;
                $postal = trim((string) ($location['zip_code'] ?? ''));
                return preg_match('/^\d{4}$/D', $postal) ? $postal : null;
            }
        } catch (\Throwable $exception) {
            // An unavailable lookup must never result in an invented postal code.
        }
        return null;
    }

    private function name(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim(preg_replace('/\bcity of\b|\bcity\b/i', '', mb_strtolower($name))));
    }
}
