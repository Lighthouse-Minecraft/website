<?php

namespace App\Enums;

enum StaffDepartment: string
{
    case Command = 'command';
    case Chaplain = 'chaplain';
    case Engineer = 'engineer';
    case Quartermaster = 'quartermaster';
    case Steward = 'steward';

    public function label(): string
    {
        return match ($this) {
            self::Command => 'Command',
            self::Chaplain => 'Chaplain',
            self::Engineer => 'Engineer',
            self::Quartermaster => 'Quartermaster',
            self::Steward => 'Steward',
        };
    }
}
