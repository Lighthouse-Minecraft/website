<?php

namespace App\Enums;

enum MinecraftAccountStatus: string
{
    case Verifying = 'verifying';
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Verifying => 'Pending Verification',
            self::Active => 'Active',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Verifying => 'yellow',
            self::Active => 'green',
            self::Cancelled => 'red',
        };
    }
}
