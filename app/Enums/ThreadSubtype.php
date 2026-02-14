<?php

namespace App\Enums;

enum ThreadSubtype: string
{
    case Support = 'support';
    case AdminAction = 'admin_action';
    case ModerationFlag = 'moderation_flag';

    public function label(): string
    {
        return match ($this) {
            self::Support => 'Support',
            self::AdminAction => 'Admin Action',
            self::ModerationFlag => 'Moderation Flag',
        };
    }
}
