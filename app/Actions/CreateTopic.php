<?php

namespace App\Actions;

use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\DisciplineReport;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\NewTopicNotification;
use App\Services\TicketNotificationService;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateTopic
{
    use AsAction;

    public function handle(
        Model $parent,
        User $creator,
        string $subject,
        ?string $initialMessage = null,
    ): Thread {
        $thread = Thread::create([
            'type' => ThreadType::Topic,
            'subtype' => null,
            'subject' => $subject,
            'status' => ThreadStatus::Open,
            'created_by_user_id' => $creator->id,
            'topicable_type' => get_class($parent),
            'topicable_id' => $parent->getKey(),
            'last_message_at' => now(),
        ]);

        // Track which user IDs we've added to deduplicate
        $addedUserIds = [];

        // Add creator as participant
        $thread->addParticipant($creator);
        $addedUserIds[] = $creator->id;

        // Auto-add participants based on parent type
        if ($parent instanceof DisciplineReport) {
            $autoAddUsers = collect();

            // Report subject
            if ($parent->subject) {
                $autoAddUsers->push($parent->subject);
            }

            // Reporter
            if ($parent->reporter) {
                $autoAddUsers->push($parent->reporter);
            }

            // Publisher
            if ($parent->publisher) {
                $autoAddUsers->push($parent->publisher);
            }

            // Subject's parents
            if ($parent->subject) {
                $parents = $parent->subject->parents()->get();
                foreach ($parents as $parentUser) {
                    $autoAddUsers->push($parentUser);
                }
            }

            // Add each unique user
            foreach ($autoAddUsers as $user) {
                if (! in_array($user->id, $addedUserIds)) {
                    $thread->addParticipant($user);
                    $addedUserIds[] = $user->id;
                }
            }
        }

        // Create system message linking to parent
        if ($parent instanceof DisciplineReport) {
            $systemUser = User::where('email', 'system@lighthouse.local')->firstOrFail();
            $parent->loadMissing(['subject', 'category']);

            $reportUrl = route('reports.show', $parent);

            $lines = [];
            $lines[] = "**Discussion started regarding [Staff Report]({$reportUrl})**";
            $lines[] = "**Subject:** {$parent->subject->name}";
            if ($parent->category) {
                $lines[] = "**Category:** {$parent->category->name}";
            }
            $lines[] = "**Severity:** {$parent->severity->label()}";
            $lines[] = "**Location:** {$parent->location->label()}";
            $lines[] = '**Date:** '.($parent->published_at ?? $parent->created_at)->format('M j, Y');

            Message::create([
                'thread_id' => $thread->id,
                'user_id' => $systemUser->id,
                'body' => implode("\n", $lines),
                'kind' => MessageKind::System,
            ]);
        }

        // Create initial message if provided
        if ($initialMessage) {
            Message::create([
                'thread_id' => $thread->id,
                'user_id' => $creator->id,
                'body' => $initialMessage,
                'kind' => MessageKind::Message,
            ]);
        }

        RecordActivity::run($thread, 'topic_created', "Created topic: {$subject}");

        // Notify all participants except creator
        $notificationService = app(TicketNotificationService::class);
        $participants = $thread->participants()
            ->where('user_id', '!=', $creator->id)
            ->with('user')
            ->get();

        foreach ($participants as $participant) {
            $notificationService->send($participant->user, new NewTopicNotification($thread));
        }

        return $thread;
    }
}
