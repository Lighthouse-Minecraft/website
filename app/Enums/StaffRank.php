<?php

namespace App\Enums;

enum StaffRank: int
{
    case JrCrew = 1;
    case Crew = 2;
    case Officer = 3;

    public function label(): string
    {
        return match($this) {
            self::JrCrew => 'Junior Crew Member',
            self::Crew => 'Crew Member',
            self::Officer => 'Officer',
        };
    }
}
