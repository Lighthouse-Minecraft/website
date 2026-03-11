<?php

namespace App\Enums;

enum QuestionSuggestionStatus: string
{
    case Suggested = 'suggested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Suggested => 'Suggested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
