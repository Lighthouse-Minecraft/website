<?php

namespace App\Enums;

enum ApplicationQuestionCategory: string
{
    case Core = 'core';
    case Officer = 'officer';
    case CrewMember = 'crew_member';
    case PositionSpecific = 'position_specific';

    public function label(): string
    {
        return match ($this) {
            self::Core => 'Core',
            self::Officer => 'Officer',
            self::CrewMember => 'Crew Member',
            self::PositionSpecific => 'Position-Specific',
        };
    }
}
