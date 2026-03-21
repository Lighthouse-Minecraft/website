<?php

namespace App\Actions;

use App\Enums\MessageFlagStatus;
use App\Enums\MessageKind;
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

            $systemUser = User::where('email', 'system@lighthouse.local')->firstOrFail();

            // Create a flag review discussion topic
            $reviewTopic = Thread::create([
                'type' => ThreadType::Topic,
                'subtype' => ThreadSubtype::ModerationFlag,
                'subject' => 'Flag Review: '.$thread->subject,
                'status' => ThreadStatus::Open,
                'created_by_user_id' => $systemUser->id,
                'last_message_at' => now(),
            ]);

            // Create system message with flag details
            $originalUrl = match ($thread->type) {
                ThreadType::Topic => "/discussions/{$thread->id}",
                ThreadType::BlogComment => $thread->topicable?->url() ?? "/discussions/{$thread->id}",
                default => "/tickets/{$thread->id}",
            };
            $originalLabel = match ($thread->type) {
                ThreadType::Topic => 'Original Discussion',
                ThreadType::BlogComment => 'Blog Post',
                default => 'Original Ticket',
            };

            $flagDetails = "**Flagged Message Review Request**\n\n";
            $flagDetails .= "**{$originalLabel}:** [".e($thread->subject)."]({$originalUrl})\n\n";
            $flagDetails .= "**Flagged Message ID:** {$message->id}\n\n";
            $flagDetails .= '**Flagged By:** ['.e($flaggingUser->name)."](/profile/{$flaggingUser->id})\n\n";
            $flagDetails .= '**Timestamp:** '.now()->format('M j, Y g:i A')."\n\n";
            $flagDetails .= "**Reason for Flag:**\n\n".e($note)."\n\n";
            $flagDetails .= "**Original Message:**\n\n> ".str_replace("\n", "\n> ", e($message->body));

            Message::create([
                'thread_id' => $reviewTopic->id,
                'user_id' => $systemUser->id,
                'body' => $flagDetails,
                'kind' => MessageKind::System,
            ]);

            // Link the flag to the review discussion
            $flag->update(['flag_review_ticket_id' => $reviewTopic->id]);

            // Add the flagging user as a participant
            $reviewTopic->addParticipant($flaggingUser);

            // Add all users who can view flagged content as participants
            $moderators = User::whereNotNull('staff_rank')
                ->get()
                ->filter(fn (User $u) => $u->can('viewFlagged', Thread::class));

            foreach ($moderators as $moderator) {
                $reviewTopic->addParticipant($moderator);
            }

            // Record activity
            RecordActivity::run($thread, 'message_flagged', "Message flagged by {$flaggingUser->name}");

            // Notify moderators
            $notificationService = app(TicketNotificationService::class);
            foreach ($moderators as $moderator) {
                $notificationService->send($moderator, new MessageFlaggedNotification($flag));
            }

            return $flag;
        });
    }
}
