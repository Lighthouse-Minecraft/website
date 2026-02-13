<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;

class PopulatePromotedAtDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:populate-promoted-at-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate promoted_at dates for users based on activity logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Populating promoted_at dates for users...');

        $users = User::all();
        $updatedCount = 0;

        foreach ($users as $user) {
            // Find the most recent promotion activity for this user
            $promotionActivity = ActivityLog::where('subject_type', User::class)
                ->where('subject_id', $user->id)
                ->whereIn('action', ['user_promoted', 'user_promoted_to_admin'])
                ->orderBy('created_at', 'desc')
                ->first();

            if ($promotionActivity) {
                // Use the promotion activity timestamp
                $user->promoted_at = $promotionActivity->created_at;
            } else {
                // Fallback to user's creation date
                $user->promoted_at = $user->created_at;
            }

            $user->save();
            $updatedCount++;
        }

        $this->info("Successfully updated {$updatedCount} users.");

        return 0;
    }
}
