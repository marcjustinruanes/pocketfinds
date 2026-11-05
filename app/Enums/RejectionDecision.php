<?php

namespace App\Enums;

enum RejectionDecision: string
{
    case AllowResubmission = 'allow_resubmission';
    case FinalRejection = 'final_rejection';

    public function label(): string
    {
        return match ($this) { self::AllowResubmission => 'Allow correction and resubmission', self::FinalRejection => 'Final rejection — no resubmission' };
    }
}
