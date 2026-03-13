<?php

namespace App\Enums;

enum CommunityResponseStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'sky',
            self::UnderReview => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'red',
            self::Archived => 'zinc',
        };
    }
}
