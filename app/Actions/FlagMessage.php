<?php

namespace App\Actions;

use App\Enums\MessageFlagStatus;
use App\Enums\MessageKind;
use App\Enums\StaffDepartment;
use App\Enums\ThreadStatus;
use App\Enums\ThreadSubtype;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\MessageFlag;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\MessageFlaggedNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FlagMessage
{
    use AsAction;

    public function handle(Message $message, User $flaggingUser, string $note): MessageFlag
    {
        return DB::transaction(function () use ($message, $flaggingUser, $note) {
            $thread = $message->thread;

            // Create the flag record
            $flag = MessageFlag::create([
                'message_id' => $message->id,
                'thread_id' => $thread->id,
                'flagged_by_user_id' => $flaggingUser->id,
                'note' => $note,
                'status' => MessageFlagStatus::New,
            ]);

            // Update original thread flags
            $thread->update([
                'is_flagged' => true,
                'has_open_flags' => true,
            ]);

            // Get or create system user
            $systemUser = User::firstOrCreate(
                ['email' => 'system@lighthouse.local'],
                ['name' => 'System']
            );

            // Create Quartermaster moderation flag ticket
            $reviewTicket = Thread::create([
                'type' => ThreadType::Ticket,
                'subtype' => ThreadSubtype::ModerationFlag,
                'department' => StaffDepartment::Quartermaster,
                'subject' => 'Flag Review: '.$thread->subject,
                'status' => ThreadStatus::Open,
                'created_by_user_id' => $systemUser->id,
            ]);

            // Create system message in review ticket with flag details
            $flagDetails = "**Flagged Message Review Request**\n\n";
            $flagDetails .= "\n**Original Ticket:** [{$thread->subject}](/tickets/{$thread->id})\n\n";
            $flagDetails .= "**Flagged Message ID:** {$message->id}\n\n";
            $flagDetails .= "**Flagged By:** [{$flaggingUser->name}](/profile/{$flaggingUser->id})\n\n";
            $flagDetails .= '**Timestamp:** '.now()->format('M j, Y g:i A')."\n\n";
            $flagDetails .= "**Reason for Flag:**\n\n{$note}\n\n";
            $flagDetails .= "\n**Original Message:**\n\n> ".str_replace("\n", "\n> ", $message->body);

            Message::create([
                'thread_id' => $reviewTicket->id,
                'user_id' => $systemUser->id,
                'body' => $flagDetails,
                'kind' => MessageKind::System,
            ]);

            // Link the flag to the review ticket
            $flag->update(['flag_review_ticket_id' => $reviewTicket->id]);

            // Record activity
            RecordActivity::run($thread, 'message_flagged', "Message flagged by {$flaggingUser->name}");

            // Notify Quartermaster staff
            $quartermasterStaff = User::where('staff_department', StaffDepartment::Quartermaster)
                ->whereNotNull('staff_rank')
                ->get();

            $notificationService = app(TicketNotificationService::class);
            foreach ($quartermasterStaff as $staff) {
                $notificationService->send($staff, new MessageFlaggedNotification($flag));
            }

            return $flag;
        });
    }
}
