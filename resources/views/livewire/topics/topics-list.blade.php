<?php

use App\Actions\ApproveBlogComment;
use App\Actions\RejectBlogComment;
use App\Enums\MessageKind;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $showArchive = false;

    public string $filter = 'active';

    #[Computed]
    public function canViewFlagged(): bool
    {
        return auth()->user()->can('viewFlagged', Thread::class);
    }

    #[Computed]
    public function canModerateComments(): bool
    {
        return auth()->user()->can('moderate-blog-comments');
    }

    #[Computed]
    public function flaggedTopics()
    {
        if (! $this->canViewFlagged) {
            return collect();
        }

        return Thread::where('type', ThreadType::Topic)
            ->where('has_open_flags', true)
            ->with(['createdBy'])
            ->orderByDesc('last_message_at')
            ->get();
    }

    #[Computed]
    public function pendingComments()
    {
        if (! $this->canModerateComments) {
            return collect();
        }

        return Message::where('is_pending_moderation', true)
            ->where('kind', MessageKind::Message)
            ->whereHas('thread', fn ($q) => $q->where('type', ThreadType::BlogComment))
            ->with(['user.minecraftAccounts', 'user.discordAccounts', 'thread.topicable'])
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function topics()
    {
        $user = auth()->user();

        return Thread::with(['createdBy', 'topicable', 'participants' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->where('type', ThreadType::Topic)
            ->where('is_locked', false)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id)->where('is_viewer', false))
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    #[Computed]
    public function archivedTopics()
    {
        $user = auth()->user();

        return Thread::with(['createdBy', 'topicable', 'participants' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->where('type', ThreadType::Topic)
            ->where('is_locked', true)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id)->where('is_viewer', false))
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    public function isUnread(Thread $thread): bool
    {
        $participant = $thread->participants
            ->where('user_id', auth()->id())
            ->first();

        if (! $participant || ! $participant->last_read_at) {
            return true;
        }

        return $thread->last_message_at > $participant->last_read_at;
    }

    public function approveComment(int $messageId): void
    {
        $this->authorize('moderate-blog-comments');

        $message = Message::findOrFail($messageId);
        ApproveBlogComment::run($message, auth()->user());

        Flux::toast('Comment approved.', 'Approved', variant: 'success');
        unset($this->pendingComments);
    }

    public function rejectComment(int $messageId): void
    {
        $this->authorize('moderate-blog-comments');

        $message = Message::findOrFail($messageId);
        RejectBlogComment::run($message, auth()->user());

        Flux::toast('Comment rejected.', 'Rejected', variant: 'success');
        unset($this->pendingComments);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Discussions</flux:heading>
        <flux:tooltip content="Coming Soon...">
            <div>
                <flux:button variant="primary" disabled>Create Discussion</flux:button>
            </div>
        </flux:tooltip>
    </div>

    <div class="flex items-center gap-2 mb-4">
        <flux:button wire:click="$set('filter', 'active')" :variant="$filter === 'active' ? 'primary' : 'ghost'" size="sm">
            My Discussions
        </flux:button>
        @if($this->canViewFlagged)
            <flux:button wire:click="$set('filter', 'flagged')" :variant="$filter === 'flagged' ? 'danger' : 'ghost'" size="sm">
                Flagged
                @if($this->flaggedTopics->count() > 0)
                    <flux:badge color="red" size="sm">{{ $this->flaggedTopics->count() }}</flux:badge>
                @endif
            </flux:button>
        @endif
        @if($this->canModerateComments)
            <flux:button wire:click="$set('filter', 'moderation')" :variant="$filter === 'moderation' ? 'primary' : 'ghost'" size="sm">
                Moderation Queue
                @if($this->pendingComments->count() > 0)
                    <flux:badge color="amber" size="sm">{{ $this->pendingComments->count() }}</flux:badge>
                @endif
            </flux:button>
        @endif
    </div>

    @if($filter === 'moderation' && $this->canModerateComments)
        {{-- Moderation Queue --}}
        @if($this->pendingComments->isNotEmpty())
            <div class="space-y-4">
                @foreach($this->pendingComments as $comment)
                    <flux:card wire:key="pending-{{ $comment->id }}">
                        <div class="flex items-start gap-3">
                            <flux:avatar size="sm" :src="$comment->user->avatarUrl()" :initials="$comment->user->initials()" class="shrink-0 mt-1" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-2 mb-1">
                                    <span class="font-semibold text-sm">{{ $comment->user->name }}</span>
                                    <flux:badge size="sm">{{ $comment->user->membership_level->label() }}</flux:badge>
                                    <span class="text-xs text-zinc-400">{{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                @if($comment->thread->topicable)
                                    <div class="mb-2 text-xs text-zinc-500">
                                        On: <a href="{{ route('blog.show', $comment->thread->topicable->slug) }}" class="text-blue-600 dark:text-blue-400 hover:underline" target="_blank">{{ $comment->thread->topicable->title }}</a>
                                    </div>
                                @endif
                                <div class="prose prose-sm dark:prose-invert max-w-none mb-3">
                                    {!! Str::markdown($comment->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                </div>
                                <div class="flex gap-2">
                                    <flux:button wire:click="approveComment({{ $comment->id }})" variant="primary" size="sm">Approve</flux:button>
                                    <flux:button wire:click="rejectComment({{ $comment->id }})" variant="danger" size="sm">Reject</flux:button>
                                </div>
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @else
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-8 text-center text-zinc-500 dark:text-zinc-400">
                <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No Pending Comments</flux:heading>
                <flux:text class="mt-2">There are no blog comments awaiting moderation.</flux:text>
            </div>
        @endif

    @elseif($filter === 'flagged' && $this->canViewFlagged)
        {{-- Flagged Discussions --}}
        @if($this->flaggedTopics->isNotEmpty())
            <div class="rounded-lg border border-red-200 dark:border-red-800 divide-y divide-red-200 dark:divide-red-800">
                @foreach($this->flaggedTopics as $topic)
                    <a
                        href="{{ route('discussions.show', $topic) }}"
                        wire:navigate
                        wire:key="flagged-{{ $topic->id }}"
                        class="block p-4 hover:bg-red-50 dark:hover:bg-red-950/30 transition"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <flux:icon.flag class="size-4 text-red-500" />
                                    <flux:heading size="sm">{{ $topic->subject }}</flux:heading>
                                </div>
                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                    <span>Started by {{ $topic->createdBy?->name ?? 'Unknown' }}</span>
                                    <span class="mx-2">&bull;</span>
                                    <span>{{ $topic->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-8 text-center text-zinc-500 dark:text-zinc-400">
                <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No Flagged Discussions</flux:heading>
                <flux:text class="mt-2">There are no discussions with open flags.</flux:text>
            </div>
        @endif
    @else

    {{-- Active Topics --}}
    @if($this->topics->isNotEmpty())
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach($this->topics as $topic)
                <a
                    href="{{ route('discussions.show', $topic) }}"
                    wire:navigate
                    wire:key="topic-{{ $topic->id }}"
                    class="block p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <flux:heading size="sm">{{ $topic->subject }}</flux:heading>
                                @if($this->isUnread($topic))
                                    <flux:badge color="blue" size="sm">New</flux:badge>
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                <span>Started by {{ $topic->createdBy?->name ?? 'Unknown' }}</span>
                                <span class="mx-2">&bull;</span>
                                <span>{{ $topic->created_at->diffForHumans() }}</span>
                                @if($topic->last_message_at)
                                    <span class="mx-2">&bull;</span>
                                    <span>Last reply {{ $topic->last_message_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-8 text-center text-zinc-500 dark:text-zinc-400">
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No Active Discussions</flux:heading>
            <flux:text class="mt-2">You don't have any active discussion topics.</flux:text>
        </div>
    @endif

    {{-- Archive Toggle --}}
    @if($this->archivedTopics->isNotEmpty())
        <div class="mt-6">
            <flux:button wire:click="$toggle('showArchive')" variant="ghost" size="sm">
                @if($showArchive)
                    Hide Archive ({{ $this->archivedTopics->count() }})
                @else
                    Show Archive ({{ $this->archivedTopics->count() }})
                @endif
            </flux:button>

            @if($showArchive)
                <div class="mt-3 rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700 opacity-75">
                    @foreach($this->archivedTopics as $topic)
                        <a
                            href="{{ route('discussions.show', $topic) }}"
                            wire:navigate
                            wire:key="archived-{{ $topic->id }}"
                            class="block p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="sm">{{ $topic->subject }}</flux:heading>
                                        <flux:badge color="red" size="sm">Locked</flux:badge>
                                    </div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                        <span>Started by {{ $topic->createdBy?->name ?? 'Unknown' }}</span>
                                        <span class="mx-2">&bull;</span>
                                        <span>{{ $topic->created_at->diffForHumans() }}</span>
                                        @if($topic->last_message_at)
                                            <span class="mx-2">&bull;</span>
                                            <span>Last reply {{ $topic->last_message_at->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @endif {{-- end filter conditional --}}
</div>
