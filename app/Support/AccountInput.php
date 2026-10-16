<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class AccountInput
{
    public static function phone(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $value = trim($value);

        return preg_match('/\A\+639[0-9]{9}\z/', $value) ? '0'.substr($value, 3) : $value;
    }

    public static function name(): array
    {
        return ['required', 'string', 'min:2', 'max:50', "regex:/\A[\p{L} .'-]+\z/u"];
    }

    public static function password(): array
    {
        return ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()];
    }
}
