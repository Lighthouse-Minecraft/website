<?php

use App\Enums\ThreadType;
use App\Models\Thread;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $showArchive = false;

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
</div>
