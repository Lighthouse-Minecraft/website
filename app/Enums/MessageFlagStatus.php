<?php

namespace App\Enums;

enum MessageFlagStatus: string
{
    case New = 'new';
    case Acknowledged = 'acknowledged';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Acknowledged => 'Acknowledged',
        };
    }
}
