<?php

namespace App\Enums;

enum MeetingType: string
{
    case StaffMeeting = 'staff_meeting';
    case BoardMeeting = 'board_meeting';
    case CommunityMeeting = 'community_meeting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::StaffMeeting => 'Staff Meeting',
            self::BoardMeeting => 'Board Meeting',
            self::CommunityMeeting => 'Community Meeting',
            self::Other => 'Other',
        };
    }
}
