<?php

namespace App\Enums;

enum ThreadType: string
{
    case Ticket = 'ticket';
    case DirectMessage = 'dm';
    case Forum = 'forum';
    case Topic = 'topic';
    case BlogComment = 'blog_comment';
    case ContactInquiry = 'contact_inquiry';
    case BrigAppeal = 'brig_appeal';

    public function label(): string
    {
        return match ($this) {
            self::Ticket => 'Ticket',
            self::DirectMessage => 'Direct Message',
            self::Forum => 'Forum',
            self::Topic => 'Topic',
            self::BlogComment => 'Blog Comment',
            self::ContactInquiry => 'Contact Inquiry',
            self::BrigAppeal => 'Brig Appeal',
        };
    }
}
