<?php

namespace App\Enums;

enum MessageKind: string
{
    case Message = 'message';
    case System = 'system';
    case InternalNote = 'internal_note';

    public function label(): string
    {
        return match ($this) {
            self::Message => 'Message',
            self::System => 'System',
            self::InternalNote => 'Internal Note',
        };
    }
}
