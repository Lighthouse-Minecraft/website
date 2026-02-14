<?php

namespace App\Console\Commands;

use App\Enums\EmailDigestFrequency;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\TicketDigestNotification;
use Illuminate\Console\Command;

class SendTicketDigests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:send-digests {frequency : daily or weekly}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send ticket digest notifications to users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $frequency = $this->argument('frequency');

        if (! in_array($frequency, ['daily', 'weekly'])) {
            $this->error('Frequency must be either "daily" or "weekly"');

            return Command::FAILURE;
        }

        $digestFrequency = $frequency === 'daily'
            ? EmailDigestFrequency::Daily
            : EmailDigestFrequency::Weekly;

        // Get users who want this digest frequency
        $users = User::where('email_digest_frequency', $digestFrequency)
            ->whereNotNull('email')
            ->get();

        $this->info("Processing {$frequency} digests for {$users->count()} users...");

        $sentCount = 0;

        foreach ($users as $user) {
            // Get threads visible to this user with activity since last digest
            // Falls back to last_notification_read_at, then account creation, then 30 days ago
            $sinceDate = $user->last_ticket_digest_sent_at
                ?? $user->last_notification_read_at
                ?? $user->created_at
                ?? now()->subDays(30);

            // Get tickets this user can access
            $ticketsQuery = Thread::query();

            if (! $user->can('viewAll', Thread::class)) {
                $ticketsQuery->where(function ($q) use ($user) {
                    $q->whereHas('participants', fn ($sq) => $sq->where('user_id', $user->id));

                    if ($user->can('viewDepartment', Thread::class) && $user->staff_department) {
                        $q->orWhere('department', $user->staff_department);
                    }

                    if ($user->can('viewFlagged', Thread::class)) {
                        $q->orWhere('is_flagged', true);
                    }
                });
            }

            $tickets = $ticketsQuery
                ->where('last_message_at', '>', $sinceDate)
                ->get();

            if ($tickets->isEmpty()) {
                continue;
            }

            // Get message counts for all tickets in a single query
            $threadIds = $tickets->pluck('id')->toArray();
            $messageCounts = Message::whereIn('thread_id', $threadIds)
                ->where('created_at', '>', $sinceDate)
                ->selectRaw('thread_id, COUNT(*) as count')
                ->groupBy('thread_id')
                ->pluck('count', 'thread_id');

            // Build ticket summary with reply counts
            $ticketSummary = $tickets->map(function ($ticket) use ($messageCounts) {
                return [
                    'subject' => $ticket->subject,
                    'count' => $messageCounts[$ticket->id] ?? 0,
                ];
            })->toArray();

            // Send the digest
            $user->notify(new TicketDigestNotification($ticketSummary));

            // Update the timestamp to prevent sending duplicates
            $user->update(['last_ticket_digest_sent_at' => now()]);

            $sentCount++;
        }

        $this->info("Sent {$sentCount} {$frequency} digest notifications.");

        return Command::SUCCESS;
    }
}
