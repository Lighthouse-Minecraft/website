<?php

namespace App\Enums;

enum DiscordAccountStatus: string
{
    case Active = 'active';
    case Brigged = 'brigged';
    case ParentDisabled = 'parent_disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Brigged => 'In the Brig',
            self::ParentDisabled => 'Disabled by Parent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Brigged => 'red',
            self::ParentDisabled => 'purple',
        };
    }
}
