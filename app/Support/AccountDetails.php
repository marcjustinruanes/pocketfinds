<?php

namespace App\Support;

use Illuminate\Http\Request;

class AccountDetails
{
    public static function name(bool $optional = false): array
    {
        return [$optional ? 'nullable' : 'required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}-]+$/u'];
    }

    public static function normalize(Request $request): void
    {
        if (is_string($request->input('contact_no'))) {
            $request->merge(['contact_no' => PhilippineMobile::normalize($request->input('contact_no'))]);
        }
    }
}
