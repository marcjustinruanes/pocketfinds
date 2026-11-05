<?php

namespace App\Support;

use App\Enums\SellerType;

class SellerDocuments
{
    public const LABELS = [
        'dti_certificate_file' => 'DTI Certificate',
        'sec_certificate_file' => 'SEC Certificate',
        'bir_certificate_file' => 'BIR Certificate of Registration (Form 2303)',
    ];

    public static function required(mixed $type): array
    {
        $type = $type instanceof SellerType ? $type->value : $type;
        return match ($type) {
            'sole_proprietorship' => ['dti_certificate_file', 'bir_certificate_file'],
            'corporation' => ['sec_certificate_file', 'bir_certificate_file'],
            default => [],
        };
    }
}
