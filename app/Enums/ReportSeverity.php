<?php

namespace App\Enums;

enum ReportSeverity: string
{
    case Commendation = 'commendation';
    case Trivial = 'trivial';
    case Minor = 'minor';
    case Moderate = 'moderate';
    case Major = 'major';
    case Severe = 'severe';

    public function label(): string
    {
        return match ($this) {
            self::Commendation => 'Commendation',
            self::Trivial => 'Trivial',
            self::Minor => 'Minor',
            self::Moderate => 'Moderate',
            self::Major => 'Major',
            self::Severe => 'Severe',
        };
    }

    public function points(): int
    {
        return match ($this) {
            self::Commendation => 0,
            self::Trivial => 1,
            self::Minor => 2,
            self::Moderate => 4,
            self::Major => 7,
            self::Severe => 10,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Commendation => 'green',
            self::Trivial => 'zinc',
            self::Minor => 'blue',
            self::Moderate => 'yellow',
            self::Major => 'orange',
            self::Severe => 'red',
        };
    }
}
