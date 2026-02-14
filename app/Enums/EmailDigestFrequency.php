<?php

namespace App\Enums;

enum EmailDigestFrequency: string
{
    case Immediate = 'immediate';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Immediate',
            self::Daily => 'Daily Digest',
            self::Weekly => 'Weekly Digest',
        };
    }
}
