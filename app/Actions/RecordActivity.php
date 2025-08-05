<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordActivity
{
    use AsAction;
    // This class will handle the recording of user activities
    // It will use the ActivityLog model to store records in the database

    public static function handle($subject, $action, $description = null)
    {
        $causerId = Auth::check() ? Auth::id() : null;

        $meta = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // Create a new activity log entry
        ActivityLog::create([
            'causer_id' => $causerId,
            'subject_type' => get_class($subject),
            'subject_id'   => $subject->getKey(),
            'action' => $action,
            'description' => $description,
            'meta' => json_encode($meta),
        ]);
    }
}
