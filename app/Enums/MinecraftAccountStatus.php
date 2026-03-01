<?php

namespace App\Enums;

enum MinecraftAccountStatus: string
{
    case Verifying = 'verifying';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Banned = 'banned';
    case Removed = 'removed';
    case ParentDisabled = 'parent_disabled';

    /**
     * Get a human-readable label for the enum case.
     */
    public function label(): string
    {
        return match ($this) {
            self::Verifying => 'Pending Verification',
            self::Active => 'Active',
            self::Cancelled => 'Cancelled',
            self::Banned => 'Banned',
            self::Removed => 'Removed',
            self::ParentDisabled => 'Disabled by Parent',
        };
    }

    /**
     * Get the color associated with the account status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Verifying => 'yellow',
            self::Active => 'green',
            self::Cancelled => 'red',
            self::Banned => 'orange',
            self::Removed => 'zinc',
            self::ParentDisabled => 'purple',
        };
    }
}
