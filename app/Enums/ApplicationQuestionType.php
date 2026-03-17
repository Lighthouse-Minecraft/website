<?php

namespace App\Enums;

enum ApplicationQuestionType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case YesNo = 'yes_no';
    case Select = 'select';

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Short Text',
            self::LongText => 'Long Text',
            self::YesNo => 'Yes / No',
            self::Select => 'Dropdown',
        };
    }
}
