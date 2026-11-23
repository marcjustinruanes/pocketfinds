<?php
namespace App\Services;

/** Provider snapshot, shared by mobile lookup and final registration validation. */
class RegistrationPostalCode
{
    public function lookup(?string $code, ?string $municipality): ?string
    {
        if (!preg_match('/^\d{9,10}$/D', (string)$code) || !$municipality) return null;
        $code = strlen($code) === 9 ? substr($code,0,2).'0'.substr($code,2) : $code;
        $source = file_get_contents(__DIR__.'/data/registration-postal-codes.json');
        $rows = json_decode(ltrim($source, "\xEF\xBB\xBF"), true) ?? [];
        foreach ($rows as $row) {
            if ((string)$row['code'] !== $code || $this->name($row['name']) !== $this->name($municipality)) continue;
            $postal = trim((string)($row['zip_code'] ?? ''));
            return preg_match('/^\d{4}$/D', $postal) ? $postal : null;
        }
        return null;
    }

    private function name(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim(preg_replace('/\bcity of\b|\bcity\b/i', '', mb_strtolower($name))));
    }
}
