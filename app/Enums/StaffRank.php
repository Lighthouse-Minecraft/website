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

    public function discordRoleId(): ?string
    {
        return match ($this) {
            self::None => null,
            self::JrCrew => config('lighthouse.discord.roles.rank_jr_crew'),
            self::CrewMember => config('lighthouse.discord.roles.rank_crew_member'),
            self::Officer => config('lighthouse.discord.roles.rank_officer'),
        };
    }
}
