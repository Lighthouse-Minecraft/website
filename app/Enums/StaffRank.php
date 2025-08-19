<?php

namespace App\Enums;

enum StaffRank: int
{
    case None = 0;
    case JrCrew = 1;
    case CrewMember = 2;
    case Officer = 3;

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::JrCrew => 'Junior Crew Member',
            self::CrewMember => 'Crew Member',
            self::Officer => 'Officer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'zinc',
            self::JrCrew => 'amber',
            self::CrewMember => 'fuchsia',
            self::Officer => 'emerald',
        };
    }
}
