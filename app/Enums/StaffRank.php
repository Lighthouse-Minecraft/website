<?php

namespace App\Enums;

enum StaffRank: int
{
    case None = 0;
    case JrCrew = 1;
    case Crew = 2;
    case Officer = 3;

    public function label(): string
    {
        return match($this) {
            self::None => 'None',
            self::JrCrew => 'Junior Crew Member',
            self::Crew => 'Crew Member',
            self::Officer => 'Officer',
        };
    }
}
