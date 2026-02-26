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

    public function discordRoleId(): ?string
    {
        return match ($this) {
            self::Command => config('lighthouse.discord.roles.staff_command'),
            self::Chaplain => config('lighthouse.discord.roles.staff_chaplain'),
            self::Engineer => config('lighthouse.discord.roles.staff_engineer'),
            self::Quartermaster => config('lighthouse.discord.roles.staff_quartermaster'),
            self::Steward => config('lighthouse.discord.roles.staff_steward'),
        };
    }
}
