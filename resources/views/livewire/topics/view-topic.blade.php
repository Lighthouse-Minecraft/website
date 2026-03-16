<?php

use App\Actions\AcknowledgeFlag;
use App\Actions\FlagMessage;
use App\Actions\RecordActivity;
use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\DisciplineReport;
use App\Models\Message;
use App\Models\MessageFlag;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\NewTopicReplyNotification;
use App\Services\TicketNotificationService;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public Thread $thread;

    public string $replyMessage = '';

    public bool $isInternalNote = false;

    public string $participantSearch = '';

    public array $searchResults = [];

    public ?int $flaggingMessageId = null;

    public string $flagReason = '';

    public ?int $acknowledgingFlagId = null;

    public string $staffNotes = '';

    public function mount(Thread $thread): void
    {
        // Only allow topic-type threads on this route
        if ($thread->type !== ThreadType::Topic) {
            abort(404);
        }

        $this->authorize('view', $thread);
        $this->thread = $thread;

        // Add viewer for read tracking
        $this->thread->addViewer(auth()->user());

        // Mark thread as read
        $participant = $this->thread->participants()
            ->where('user_id', auth()->id())
            ->first();

        if ($participant) {
            $participant->update(['last_read_at' => now()]);
            auth()->user()->clearTicketCaches();
            Thread::clearUnreadCache(auth()->user(), ThreadType::Topic);
        }
    }

    public function checkForNewMessages(): void
    {
        $fresh = $this->thread->fresh();

        if ($fresh->last_message_at && $fresh->last_message_at->gt($this->thread->last_message_at)) {
            $this->thread = $fresh;
            unset($this->messages);

            // Mark as read since the user is actively viewing this thread
            $this->thread->participants()
                ->where('user_id', auth()->id())
                ->update(['last_read_at' => now()]);
            Thread::clearUnreadCache(auth()->user(), ThreadType::Topic);
        }
    }

    #[Computed]
    public function messages()
    {
        $messages = $this->thread->messages()
            ->with(['user.minecraftAccounts', 'user.discordAccounts', 'flags.flaggedBy', 'flags.reviewedBy'])
            ->orderBy('created_at')
            ->get();

        if (! auth()->user()->can('internalNotes', $this->thread)) {
            $messages = $messages->filter(fn ($msg) => $msg->kind !== MessageKind::InternalNote);
        }

        return $messages;
    }

    #[Computed]
    public function canReply(): bool
    {
        // Refresh to catch locks/status changes from other sessions
        $this->thread->refresh();

        if ($this->thread->is_locked || $this->thread->status === ThreadStatus::Closed) {
            return false;
        }

        return auth()->user()->can('reply', $this->thread);
    }

    #[Computed]
    public function canAddInternalNotes(): bool
    {
        return auth()->user()->can('internalNotes', $this->thread);
    }

    #[Computed]
    public function canLock(): bool
    {
        return Gate::allows('lock-topic') && $this->thread->isVisibleTo(auth()->user());
    }

    #[Computed]
    public function canAddParticipant(): bool
    {
        return auth()->user()->can('addParticipant', $this->thread);
    }

    #[Computed]
    public function canViewFlagged(): bool
    {
        return auth()->user()->can('viewFlagged', Thread::class);
    }

    #[Computed]
    public function parentModel()
    {
        $parent = $this->thread->topicable;

        if ($parent instanceof DisciplineReport) {
            $parent->load(['subject', 'reporter', 'publisher', 'category']);
        }

        return $parent;
    }

    #[Computed]
    public function participantsList()
    {
        return $this->thread->participants()
            ->with(['user.minecraftAccounts', 'user.discordAccounts'])
            ->get();
    }

    public function sendReply(): void
    {
        $validator = Validator::make(
            ['replyMessage' => $this->replyMessage],
            ['replyMessage' => 'required|string|min:1']
        );

        if ($validator->fails()) {
            $this->addError('replyMessage', $validator->errors()->first('replyMessage'));
            return;
        }

        // Refresh to catch locks from other sessions/actions
        $this->thread->refresh();
        $this->authorize('reply', $this->thread);

        $kind = MessageKind::Message;

        if ($this->isInternalNote) {
            $this->authorize('internalNotes', $this->thread);
            $kind = MessageKind::InternalNote;
        }

        $now = now();

        $message = Message::create([
            'thread_id' => $this->thread->id,
            'user_id' => auth()->id(),
            'body' => $this->replyMessage,
            'kind' => $kind,
        ]);

        // Ensure sender is a full participant
        $existingParticipant = $this->thread->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (! $existingParticipant) {
            $this->thread->addParticipant(auth()->user(), isViewer: false);
            $existingParticipant = $this->thread->participants()
                ->where('user_id', auth()->id())
                ->first();
        } elseif ($existingParticipant->is_viewer) {
            $existingParticipant->update(['is_viewer' => false]);
        }

        $existingParticipant->update(['last_read_at' => $now]);
        $this->thread->update(['last_message_at' => $now]);

        // Clear sender's unread cache immediately
        Thread::clearUnreadCache(auth()->user(), ThreadType::Topic);

        // Notify non-viewer participants (except sender) and clear their caches
        if ($kind !== MessageKind::InternalNote) {
            $participants = $this->thread->participants()
                ->where('user_id', '!=', auth()->id())
                ->where('is_viewer', false)
                ->with('user')
                ->get();

            $notificationService = app(TicketNotificationService::class);
            foreach ($participants as $participant) {
                $notificationService->send($participant->user, new NewTopicReplyNotification($message));
                Thread::clearUnreadCache($participant->user, ThreadType::Topic);
            }
        }

        $this->replyMessage = '';
        $this->isInternalNote = false;

        Flux::toast('Reply sent successfully!', variant: 'success');

        unset($this->messages);
        unset($this->participantsList);
    }

    public function toggleLock(): void
    {
        Gate::authorize('lock-topic');

        $newLockState = ! $this->thread->is_locked;
        $this->thread->update(['is_locked' => $newLockState]);

        // Create system message
        $systemUser = User::where('email', 'system@lighthouse.local')->firstOrFail();
        $action = $newLockState ? 'locked' : 'unlocked';

        Message::create([
            'thread_id' => $this->thread->id,
            'user_id' => $systemUser->id,
            'body' => auth()->user()->name . " {$action} this topic.",
            'kind' => MessageKind::System,
        ]);

        $now = now();
        $this->thread->update(['last_message_at' => $now]);

        // Mark as read for the user who triggered the action
        $this->thread->participants()
            ->where('user_id', auth()->id())
            ->update(['last_read_at' => $now]);
        Thread::clearUnreadCache(auth()->user(), ThreadType::Topic);

        RecordActivity::run($this->thread, "topic_{$action}", "Topic {$action} by " . auth()->user()->name);

        Flux::toast("Topic {$action} successfully!", variant: 'success');

        unset($this->messages);
        unset($this->canReply);
    }

    public function searchUsers(): void
    {
        $this->authorize('addParticipant', $this->thread);

        if (strlen($this->participantSearch) < 2) {
            $this->searchResults = [];
            return;
        }

        $term = $this->participantSearch;

        // Get existing participant IDs to exclude
        $existingIds = $this->thread->participants()->pluck('user_id')->toArray();

        $users = User::where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhereHas('discordAccounts', fn ($q) => $q->where('username', 'like', "%{$term}%"))
                    ->orWhereHas('minecraftAccounts', fn ($q) => $q->where('username', 'like', "%{$term}%"));
            })
            ->whereNotIn('id', $existingIds)
            ->limit(10)
            ->get(['id', 'name']);

        $this->searchResults = $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->toArray();
    }

    public function addParticipantById(int $userId): void
    {
        $this->authorize('addParticipant', $this->thread);

        $user = User::findOrFail($userId);
        $this->thread->addParticipant($user);

        // Create system message
        $systemUser = User::where('email', 'system@lighthouse.local')->firstOrFail();
        Message::create([
            'thread_id' => $this->thread->id,
            'user_id' => $systemUser->id,
            'body' => auth()->user()->name . " added {$user->name} to this topic.",
            'kind' => MessageKind::System,
        ]);

        $this->thread->update(['last_message_at' => now()]);

        RecordActivity::run($this->thread, 'participant_added', "{$user->name} added by " . auth()->user()->name);

        $this->participantSearch = '';
        $this->searchResults = [];

        Flux::toast("{$user->name} added to topic.", variant: 'success');

        unset($this->messages);
        unset($this->participantsList);
    }

    public function openFlagModal(int $messageId): void
    {
        $message = Message::findOrFail($messageId);
        $this->authorize('flag', $message);

        $this->flaggingMessageId = $messageId;
        $this->flagReason = '';

        Flux::modal('flag-message')->show();
    }

    public function submitFlag(): void
    {
        $validator = Validator::make(
            ['flagReason' => $this->flagReason],
            ['flagReason' => 'required|string|min:10']
        );

        if ($validator->fails()) {
            $this->addError('flagReason', $validator->errors()->first('flagReason'));

            return;
        }

        $message = Message::findOrFail($this->flaggingMessageId);
        $this->authorize('flag', $message);

        FlagMessage::run($message, auth()->user(), $this->flagReason);

        $this->flaggingMessageId = null;
        $this->flagReason = '';

        Flux::modal('flag-message')->close();
        Flux::toast('Message flagged for review. Staff will be notified.', variant: 'success');

        unset($this->messages);
    }

    public function openAcknowledgeModal(int $flagId): void
    {
        if (! $this->canViewFlagged) {
            abort(403);
        }

        $this->acknowledgingFlagId = $flagId;
        $this->staffNotes = '';

        Flux::modal('acknowledge-flag')->show();
    }

    public function acknowledgeFlag(): void
    {
        if (! $this->canViewFlagged) {
            abort(403);
        }

        $flag = MessageFlag::findOrFail($this->acknowledgingFlagId);

        AcknowledgeFlag::run($flag, auth()->user(), $this->staffNotes ?: null);

        $this->acknowledgingFlagId = null;
        $this->staffNotes = '';

        Flux::modal('acknowledge-flag')->close();
        Flux::toast('Flag acknowledged successfully!', variant: 'success');

        unset($this->messages);
    }
}; ?>

<div class="space-y-6">
    {{-- Topic Header --}}
    <div class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $thread->subject }}</flux:heading>
                @if($thread->is_locked)
                    <flux:badge color="red" size="sm">Locked</flux:badge>
                @endif
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                <span>Started by: <a href="{{ route('profile.show', $thread->createdBy) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $thread->createdBy->name }}</a></span>
                <span>&bull;</span>
                <span>Created {{ $thread->created_at->diffForHumans() }}</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($this->canLock)
                <flux:button wire:click="toggleLock" variant="ghost" size="sm">
                    @if($thread->is_locked)
                        Unlock Topic
                    @else
                        Lock Topic
                    @endif
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Parent Report Summary --}}
    @if($this->parentModel instanceof \App\Models\DisciplineReport)
        @php $report = $this->parentModel; @endphp
        <flux:card class="bg-zinc-50 dark:bg-zinc-900">
            <div class="flex items-center gap-3 mb-2">
                <flux:heading size="sm">Related Staff Report</flux:heading>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span>Subject: <a href="{{ route('profile.show', $report->subject) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $report->subject->name }}</a></span>
                @if($report->category)
                    <flux:badge color="{{ $report->category->color }}" size="sm">{{ $report->category->name }}</flux:badge>
                @endif
                <flux:badge color="{{ $report->severity->color() }}" size="sm">{{ $report->severity->label() }}</flux:badge>
                <span class="text-zinc-500">{{ ($report->published_at ?? $report->created_at)->format('M j, Y') }}</span>
            </div>
        </flux:card>
    @endif

    {{-- Participants --}}
    <flux:card>
        <div class="flex items-center justify-between mb-3">
            <flux:heading size="sm">Participants ({{ $this->participantsList->count() }})</flux:heading>
            @if($this->canAddParticipant)
                <flux:button size="xs" variant="ghost" x-on:click="$flux.modal('add-participant').show()">
                    Add Participant
                </flux:button>
            @endif
        </div>
        <div class="flex flex-wrap gap-3">
            @foreach($this->participantsList as $participant)
                <div wire:key="participant-{{ $participant->id }}" class="flex items-center gap-2">
                    <flux:avatar size="xs" :src="$participant->user->avatarUrl()" :initials="$participant->user->initials()" />
                    <a href="{{ route('profile.show', $participant->user) }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">{{ $participant->user->name }}</a>
                    @if($participant->is_viewer)
                        <flux:badge size="sm" color="zinc">Viewer</flux:badge>
                    @endif
                </div>
            @endforeach
        </div>
    </flux:card>

    {{-- Messages --}}
    <div class="mx-auto w-full max-w-3xl" wire:poll.15s="checkForNewMessages">
    <div class="flex flex-col gap-4">
        @php $tz = auth()->user()->timezone ?? 'UTC'; @endphp
        @foreach($this->messages as $message)
            @php $isOwn = $message->user_id === auth()->id(); @endphp

            @if($message->kind === \App\Enums\MessageKind::System)
                {{-- System Message — centered --}}
                <div wire:key="message-{{ $message->id }}" class="chat-system">
                    <div class="inline-block rounded-lg bg-zinc-100 dark:bg-zinc-800 px-4 py-2">
                        <div class="text-sm italic text-zinc-600 dark:text-zinc-400 prose prose-sm dark:prose-invert max-w-none">{!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    </div>
                    <div class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->setTimezone($tz)->format('M j, Y g:i A') }}</div>
                </div>

            @elseif($message->kind === \App\Enums\MessageKind::InternalNote)
                {{-- Internal Note — always left-aligned, amber --}}
                <div wire:key="message-{{ $message->id }}" class="chat-message chat-message-start">
                    <flux:avatar size="sm" :src="$message->user->avatarUrl()" :initials="$message->user->initials()" class="shrink-0 mt-1" />
                    <div class="min-w-0">
                        <div class="flex items-baseline gap-2 mb-1">
                            <a href="{{ route('profile.show', $message->user) }}" class="font-semibold text-sm text-blue-600 dark:text-blue-400 hover:underline">{{ $message->user->name }}</a>
                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->setTimezone($tz)->format('M j, Y g:i A') }}</span>
                            <flux:badge size="sm" color="amber">Internal Note</flux:badge>
                        </div>
                        @if($message->user->staff_rank && $message->user->staff_rank !== \App\Enums\StaffRank::None)
                            <div class="mb-1">
                                <flux:badge size="sm" :color="$message->user->staff_rank->color()">{{ $message->user->staff_department?->label() }} &middot; {{ $message->user->staff_rank->label() }}</flux:badge>
                            </div>
                        @endif
                        <div class="chat-bubble chat-bubble-start border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30">
                            <div class="prose prose-sm dark:prose-invert max-w-none [&_a]:text-blue-600 dark:[&_a]:text-blue-400 [&_a]:underline [&_a]:font-medium hover:[&_a]:text-blue-700 dark:hover:[&_a]:text-blue-300">
                                {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </div>
                    </div>
                </div>

            @elseif($isOwn)
                {{-- Own message — right-aligned, cyan --}}
                <div wire:key="message-{{ $message->id }}" class="chat-message chat-message-end">
                    <flux:avatar size="sm" :src="$message->user->avatarUrl()" :initials="$message->user->initials()" class="shrink-0 mt-1" />
                    <div class="min-w-0">
                        <div class="flex items-baseline gap-2 mb-1 justify-end">
                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->setTimezone($tz)->format('M j, Y g:i A') }}</span>
                        </div>
                        <div class="chat-bubble chat-bubble-end bg-cyan-50 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800">
                            <div class="prose prose-sm dark:prose-invert max-w-none [&_a]:text-blue-600 dark:[&_a]:text-blue-400 [&_a]:underline [&_a]:font-medium hover:[&_a]:text-blue-700 dark:hover:[&_a]:text-blue-300">
                                {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </div>
                        @include('livewire.topics.partials.flag-display', ['message' => $message, 'tz' => $tz])
                    </div>
                </div>

            @else
                {{-- Other user's message — left-aligned, neutral --}}
                <div wire:key="message-{{ $message->id }}" class="chat-message chat-message-start">
                    <flux:avatar size="sm" :src="$message->user->avatarUrl()" :initials="$message->user->initials()" class="shrink-0 mt-1" />
                    <div class="min-w-0">
                        <div class="flex items-baseline gap-2 mb-1">
                            <a href="{{ route('profile.show', $message->user) }}" class="font-semibold text-sm text-blue-600 dark:text-blue-400 hover:underline">{{ $message->user->name }}</a>
                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->setTimezone($tz)->format('M j, Y g:i A') }}</span>
                            @if(auth()->user()->can('flag', $message))
                                <flux:button wire:click="openFlagModal({{ $message->id }})" variant="ghost" size="xs" class="!p-0.5" aria-label="Flag message">
                                    <flux:icon.flag class="size-3.5" />
                                </flux:button>
                            @endif
                        </div>
                        @if($message->user->staff_rank && $message->user->staff_rank !== \App\Enums\StaffRank::None)
                            <div class="mb-1">
                                <flux:badge size="sm" :color="$message->user->staff_rank->color()">{{ $message->user->staff_department?->label() }} &middot; {{ $message->user->staff_rank->label() }}</flux:badge>
                            </div>
                        @endif
                        <div class="chat-bubble chat-bubble-start bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                            <div class="prose prose-sm dark:prose-invert max-w-none [&_a]:text-blue-600 dark:[&_a]:text-blue-400 [&_a]:underline [&_a]:font-medium hover:[&_a]:text-blue-700 dark:hover:[&_a]:text-blue-300">
                                {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </div>
                        @include('livewire.topics.partials.flag-display', ['message' => $message, 'tz' => $tz])
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Reply Form --}}
    @if($this->canReply)
        <form wire:submit="sendReply" class="mt-6">
            <flux:composer wire:model="replyMessage" submit="enter" rows="2" max-rows="6" label="Reply" label:sr-only placeholder="Type your reply...">
                <x-slot name="actionsLeading">
                    @if($this->canAddInternalNotes)
                        <flux:checkbox wire:model="isInternalNote" label="Internal Note" />
                    @endif
                </x-slot>
                <x-slot name="actionsTrailing">
                    <flux:button type="submit" size="sm" variant="primary" icon="paper-airplane" aria-label="Send reply" />
                </x-slot>
            </flux:composer>
            <flux:error name="replyMessage" />
        </form>
    @elseif($thread->is_locked)
        <div class="mt-6 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4 text-center">
            <flux:text variant="subtle">This topic is locked. No new replies can be posted.</flux:text>
        </div>
    @endif
    </div> {{-- end max-w-3xl chat container --}}

    {{-- Add Participant Modal --}}
    <flux:modal name="add-participant" class="w-full md:w-1/3">
        <div class="space-y-4">
            <flux:heading size="lg">Add Participant</flux:heading>
            <flux:text variant="subtle">Search by name, Discord username, or Minecraft username.</flux:text>

            <flux:field>
                <flux:input wire:model="participantSearch" wire:keyup.debounce.300ms="searchUsers" placeholder="Search users..." />
            </flux:field>

            @if(count($searchResults) > 0)
                <div class="space-y-2">
                    @foreach($searchResults as $result)
                        <div wire:key="search-result-{{ $result['id'] }}" class="flex items-center justify-between rounded border border-zinc-200 dark:border-zinc-700 p-2">
                            <flux:text>{{ $result['name'] }}</flux:text>
                            <flux:button size="xs" variant="primary" wire:click="addParticipantById({{ $result['id'] }})">Add</flux:button>
                        </div>
                    @endforeach
                </div>
            @elseif(strlen($participantSearch) >= 2)
                <flux:text variant="subtle">No users found.</flux:text>
            @endif
        </div>
    </flux:modal>

    {{-- Flag Message Modal --}}
    <flux:modal name="flag-message" class="space-y-6">
        <div>
            <flux:heading size="lg">Flag Message</flux:heading>
            <flux:subheading>Why are you flagging this message?</flux:subheading>
        </div>

        <flux:field>
            <flux:label>Reason <span class="text-red-500">*</span></flux:label>
            <flux:textarea wire:model="flagReason" rows="4" placeholder="Please explain why this message should be reviewed by staff..." />
            <flux:error name="flagReason" />
        </flux:field>

        <div class="flex justify-end">
            <flux:button wire:click="submitFlag" variant="danger">Submit Flag</flux:button>
        </div>
    </flux:modal>

    {{-- Acknowledge Flag Modal (staff only) --}}
    @if($this->canViewFlagged)
        <flux:modal name="acknowledge-flag" class="space-y-6">
            <div>
                <flux:heading size="lg">Acknowledge Flag</flux:heading>
                <flux:subheading>Add notes about your review of this flag (optional)</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Staff Notes</flux:label>
                <flux:textarea wire:model="staffNotes" rows="4" placeholder="Add any notes about your review of this flag..." />
            </flux:field>

            <div class="flex justify-end">
                <flux:button wire:click="acknowledgeFlag" variant="primary">Acknowledge Flag</flux:button>
            </div>
        </flux:modal>
    @endif
</div>
