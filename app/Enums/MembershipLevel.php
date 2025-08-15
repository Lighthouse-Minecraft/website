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
}
