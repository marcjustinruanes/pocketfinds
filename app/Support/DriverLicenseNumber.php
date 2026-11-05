<?php

namespace App\Support;

final class DriverLicenseNumber
{
    public static function exists(string $value, ?int $ignoreId=null): bool
    {
        $compact=str_replace(['-',' '],'',self::normalize($value));
        return \App\Models\User::whereRaw("UPPER(REPLACE(REPLACE(TRIM(license_number), '-', ''), ' ', '')) = ?",[$compact])
            ->where('status','!=','rejected')->when($ignoreId,fn($query)=>$query->where('id','!=',$ignoreId))->exists();
    }

    public static function normalize(string $value): string
    {
        $value=strtoupper(trim($value));
        $compact=preg_replace('/[\s-]+/', '', $value);
        return preg_match('/^([A-Z]\d{2})(\d{2})(\d{6})$/D', $compact, $parts)
            ? $parts[1].'-'.$parts[2].'-'.$parts[3]
            : $value;
    }
}
