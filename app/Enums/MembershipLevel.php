<?php

namespace App\Enums;

enum MembershipLevel: int
{
    case Drifter = 0;
    case Stowaway = 1;
    case Traveler = 2;
    case Resident = 3;
    case Citizen = 4;

    public function label(): string
    {
        return match ($this) {
            self::Drifter => 'Drifter',
            self::Stowaway => 'Stowaway',
            self::Traveler => 'Traveler',
            self::Resident => 'Resident',
            self::Citizen => 'Citizen',
        };
    }

    /**
     * The Discord role ID for this membership level.
     * Returns null for levels without a Discord role (Drifter, Stowaway).
     */
    public function discordRoleId(): ?string
    {
        return match ($this) {
            self::Drifter, self::Stowaway => null,
            self::Traveler => config('lighthouse.discord.roles.traveler'),
            self::Resident => config('lighthouse.discord.roles.resident'),
            self::Citizen => config('lighthouse.discord.roles.citizen'),
        };
    }

    public function minecraftRank(): ?string
    {
        return match ($this) {
            self::Drifter, self::Stowaway => null,
            self::Traveler => 'traveler',
            self::Resident => 'resident',
            self::Citizen => 'citizen',
        };
    }
}
