<?php

namespace App\Support;

use App\Models\User;

class ApplicationCorrection
{
    public static function fingerprint(User $user): string
    {
        return hash('sha256', $user->password.'|'.$user->status_reason.'|'.$user->updated_at?->format('Y-m-d H:i:s'));
    }

    public static function grant(User $user): array
    {
        return ['id'=>$user->id, 'expires_at'=>now()->addMinutes(30)->timestamp, 'fingerprint'=>self::fingerprint($user)];
    }
}
