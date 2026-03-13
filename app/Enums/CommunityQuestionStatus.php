<?php

namespace App\Enums;

enum CommunityQuestionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Scheduled => 'sky',
            self::Active => 'emerald',
            self::Archived => 'amber',
        };
    }
}
