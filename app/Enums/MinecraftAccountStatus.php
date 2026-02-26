<?php

namespace App\Enums;

enum MinecraftAccountStatus: string
{
    case Verifying = 'verifying';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Banned = 'banned';
    case Removed = 'removed';

    /**
     * Get a human-readable label for the enum case.
     *
     * @return string The human-readable label corresponding to the enum case.
     */
    public function label(): string
    {
        return match ($this) {
            self::Verifying => 'Pending Verification',
            self::Active => 'Active',
            self::Cancelled => 'Cancelled',
            self::Banned => 'Banned',
            self::Removed => 'Removed',
        };
    }

    /**
     * Get the color associated with the account status.
     *
     * @return string The color name for the status: 'yellow' for Verifying, 'green' for Active, 'red' for Cancelled, or 'orange' for Banned.
     */
    public function color(): string
    {
        return match ($this) {
            self::Verifying => 'yellow',
            self::Active => 'green',
            self::Cancelled => 'red',
            self::Banned => 'orange',
            self::Removed => 'zinc',
        };
    }
}
