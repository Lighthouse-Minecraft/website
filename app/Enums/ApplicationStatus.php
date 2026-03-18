<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Interview = 'interview';
    case BackgroundCheck = 'background_check';
    case Approved = 'approved';
    case Denied = 'denied';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Interview => 'Interview',
            self::BackgroundCheck => 'Background Check',
            self::Approved => 'Approved',
            self::Denied => 'Denied',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'blue',
            self::UnderReview => 'amber',
            self::Interview => 'purple',
            self::BackgroundCheck => 'sky',
            self::Approved => 'emerald',
            self::Denied => 'red',
            self::Withdrawn => 'zinc',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Denied, self::Withdrawn]);
    }
}
