<?php

namespace App\Enums;

enum BrigType: string
{
    case Discipline = 'discipline';
    case ParentalPending = 'parental_pending';
    case ParentalDisabled = 'parental_disabled';
    case AgeLock = 'age_lock';
    case RulesNonCompliance = 'rules_non_compliance';

    public function label(): string
    {
        return match ($this) {
            self::Discipline => 'Disciplinary',
            self::ParentalPending => 'Pending Parental Approval',
            self::ParentalDisabled => 'Restricted by Parent',
            self::AgeLock => 'Age Verification Required',
            self::RulesNonCompliance => 'Rules Non-Compliance',
        };
    }

    public function isDisciplinary(): bool
    {
        return $this === self::Discipline;
    }

    public function isParental(): bool
    {
        return in_array($this, [self::ParentalPending, self::ParentalDisabled]);
    }
}
