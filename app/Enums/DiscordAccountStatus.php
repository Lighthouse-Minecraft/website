<?php

namespace App\Enums;

enum DiscordAccountStatus: string
{
    case Active = 'active';
    case Brigged = 'brigged';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Brigged => 'In the Brig',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Brigged => 'red',
        };
    }
}
