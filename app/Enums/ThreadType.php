<?php

namespace App\Enums;

enum ThreadType: string
{
    case Ticket = 'ticket';
    case DirectMessage = 'dm';
    case Forum = 'forum';
    case Topic = 'topic';

    public function label(): string
    {
        return match ($this) {
            self::Ticket => 'Ticket',
            self::DirectMessage => 'Direct Message',
            self::Forum => 'Forum',
            self::Topic => 'Topic',
        };
    }
}
