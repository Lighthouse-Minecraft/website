<?php

namespace App\Enums;

enum BackgroundCheckStatus: string
{
    case Pending = 'pending';
    case Deliberating = 'deliberating';
    case Passed = 'passed';
    case Failed = 'failed';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Deliberating => 'Deliberating',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::Waived => 'Waived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Deliberating => 'violet',
            self::Passed => 'emerald',
            self::Failed => 'red',
            self::Waived => 'zinc',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Passed, self::Failed, self::Waived => true,
            self::Pending, self::Deliberating => false,
        };
    }
}
