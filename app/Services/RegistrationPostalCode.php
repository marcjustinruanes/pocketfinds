<?php

namespace App\Services;

class RegistrationPostalCode
{
    public function lookup(?string $code, ?string $municipality, ?string $province = null): ?string
    {
        if (!preg_match('/^\d{9,10}$/D', (string) $code) || !$municipality || !$province) return null;
        $directory = json_decode(file_get_contents(resource_path('data/postal-codes.json')), true, 512, JSON_THROW_ON_ERROR);
        $codes = [];
        foreach ($directory['locations'] as $location) {
            if ($this->name($location['province']) !== $this->name($province)
                || $this->name($location['municipality']) !== $this->name($municipality)) continue;
            $codes[] = $location['postal_code'];
        }
        $codes = array_values(array_unique($codes));
        // District-specific or missing codes require the user's local address knowledge.
        return count($codes) === 1 ? $codes[0] : null;
    }

    private function name(string $name): string
    {
        $name = preg_replace('/\bsta\.?\s+/iu', 'santa ', $name);
        $name = preg_replace('/\bsto\.?\s+/iu', 'santo ', $name);
        $name = preg_replace('/\s*\(capital\)/iu', '', $name);
        return preg_replace('/\s+/', ' ', trim(preg_replace('/\bcity of\b|\bcity\b/i', '', mb_strtolower($name))));
    }
}
