<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Finalizing = 'finalizing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Finalizing => 'Finalizing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Archived => 'Archived'
        };
    }
}
