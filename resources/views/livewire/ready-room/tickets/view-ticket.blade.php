<?php

use App\Actions\AcknowledgeFlag;
use App\Actions\FlagMessage;
use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Models\Message;
use App\Models\MessageFlag;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\NewTicketReplyNotification;
use App\Notifications\TicketAssignedNotification;
use App\Services\TicketNotificationService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {
    public Thread $thread;

    #[Validate('required|string|min:1')]
    public string $replyMessage = '';

    public bool $isInternalNote = false;

    public ?int $flaggingMessageId = null;

    #[Validate('required|string|min:10')]
    public string $flagReason = '';

    public ?int $acknowledgingFlagId = null;

    public string $staffNotes = '';

    public function mount(Thread $thread): void
    {
        $this->authorize('view', $thread);
        $this->thread = $thread;
    }

    #[Computed]
    public function messages()
    {
        $messages = $this->thread->messages()
            ->with(['user', 'flags.flaggedBy', 'flags.reviewedBy'])
            ->orderBy('created_at')
            ->get();

        // Filter out internal notes for non-staff
        if (! auth()->user()->can('internalNotes', $this->thread)) {
            $messages = $messages->filter(fn($msg) => $msg->kind !== MessageKind::InternalNote);
        }

        return $messages;
    }

    #[Computed]
    public function canReply(): bool
    {
        return auth()->user()->can('reply', $this->thread);
    }

    #[Computed]
    public function canAddInternalNotes(): bool
    {
        return auth()->user()->can('internalNotes', $this->thread);
    }

    #[Computed]
    public function canChangeStatus(): bool
    {
        return auth()->user()->can('changeStatus', $this->thread);
    }

    #[Computed]
    public function canAssign(): bool
    {
        return auth()->user()->can('assign', $this->thread);
    }

    #[Computed]
    public function canViewFlagged(): bool
    {
        return auth()->user()->can('viewFlagged', Thread::class);
    }

    #[Computed]
    public function staffUsers()
    {
        return User::whereNotNull('staff_rank')
            ->where('staff_department', $this->thread->department)
            ->orderBy('name')
            ->get();
    }

    public function sendReply(): void
    {
        $this->authorize('reply', $this->thread);
        $this->validate(['replyMessage' => 'required|string|min:1']);

        $kind = MessageKind::Message;

        if ($this->isInternalNote) {
            $this->authorize('internalNotes', $this->thread);
            $kind = MessageKind::InternalNote;
        }

        $message = Message::create([
            'thread_id' => $this->thread->id,
            'user_id' => auth()->id(),
            'body' => $this->replyMessage,
            'kind' => $kind,
        ]);

        // Add sender as participant if not already
        $this->thread->addParticipant(auth()->user());

        // Update thread last message time
        $this->thread->update(['last_message_at' => now()]);

        // Record activity
        $activityType = $kind === MessageKind::InternalNote ? 'internal_note_added' : 'message_sent';
        \App\Actions\RecordActivity::run($this->thread, $activityType, 'New message added to thread');

        // Notify participants (except sender and for internal notes)
        if ($kind !== MessageKind::InternalNote) {
            $participants = $this->thread->participants()
                ->where('user_id', '!=', auth()->id())
                ->with('user')
                ->get();

            $notificationService = app(TicketNotificationService::class);
            foreach ($participants as $participant) {
                $notificationService->send($participant->user, new NewTicketReplyNotification($message));
            }
        }

        $this->replyMessage = '';
        $this->isInternalNote = false;

        Flux::toast('Reply sent successfully!', variant: 'success');

        unset($this->messages);
    }

    public function changeStatus(string $newStatus): void
    {
        $this->authorize('changeStatus', $this->thread);

        $oldStatus = $this->thread->status;
        $this->thread->update(['status' => ThreadStatus::from($newStatus)]);

        \App\Actions\RecordActivity::run(
            $this->thread,
            'status_changed',
            "Status changed: {$oldStatus->label()} → {$this->thread->status->label()}"
        );

        Flux::toast('Status updated successfully!', variant: 'success');
    }

    public function assignTo(?int $userId): void
    {
        $this->authorize('assign', $this->thread);

        $oldAssignee = $this->thread->assignedTo;
        $this->thread->update(['assigned_to_user_id' => $userId]);

        $newAssignee = $userId ? User::find($userId) : null;

        $description = $oldAssignee
            ? "Assignment changed: {$oldAssignee->name} → ".($newAssignee?->name ?? 'Unassigned')
            : "Assigned to: ".($newAssignee?->name ?? 'Unassigned');

        \App\Actions\RecordActivity::run($this->thread, 'assignment_changed', $description);

        // Notify both the new assignee and the ticket creator
        if ($newAssignee) {
            $notificationService = app(TicketNotificationService::class);
            $notificationService->send($newAssignee, new TicketAssignedNotification($this->thread));

            if ($this->thread->createdBy && $this->thread->createdBy->id !== $newAssignee->id) {
                $notificationService->send($this->thread->createdBy, new TicketAssignedNotification($this->thread));
            }
        }

        Flux::toast('Ticket assigned successfully!', variant: 'success');
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
        $this->validate(['flagReason' => 'required|string|min:10']);

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
        $flag = MessageFlag::findOrFail($flagId);

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
    {{-- Thread Header --}}
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl">{{ $thread->subject }}</flux:heading>
            <div class="mt-2 flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400">
                <span>{{ $thread->department->label() }}</span>
                <span>•</span>
                <span>{{ $thread->subtype->label() }}</span>
                <span>•</span>
                <span>Created {{ $thread->created_at->diffForHumans() }}</span>
            </div>
        </div>
        <flux:button href="/ready-room/tickets" variant="ghost" size="sm">← Back to Tickets</flux:button>
    </div>

    {{-- Status & Assignment Controls (Staff Only) --}}
    @if($this->canChangeStatus || $this->canAssign)
        <div class="flex items-center gap-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4">
            @if($this->canChangeStatus)
                <flux:field class="flex-1">
                    <flux:label>Status</flux:label>
                    <flux:select wire:change="changeStatus($event.target.value)" value="{{ $thread->status->value }}" variant="listbox">
                        <flux:option value="open">Open</flux:option>
                        <flux:option value="pending">Pending</flux:option>
                        <flux:option value="resolved">Resolved</flux:option>
                        <flux:option value="closed">Closed</flux:option>
                    </flux:select>
                </flux:field>
            @endif

            @if($this->canAssign)
                <flux:field class="flex-1">
                    <flux:label>Assigned To</flux:label>
                    <flux:select wire:change="assignTo($event.target.value ? parseInt($event.target.value) : null)" value="{{ $thread->assigned_to_user_id }}" variant="listbox">
                        <flux:option value="">Unassigned</flux:option>
                        @foreach($this->staffUsers as $staff)
                            <flux:option value="{{ $staff->id }}">{{ $staff->name }}</flux:option>
                        @endforeach
                    </flux:select>
                </flux:field>
            @endif
        </div>
    @endif

    {{-- Messages --}}
    <div class="space-y-4">
        @foreach($this->messages as $message)
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 @if($message->kind === \App\Enums\MessageKind::InternalNote) bg-amber-50 dark:bg-amber-950 border-amber-300 dark:border-amber-700 @elseif($message->kind === \App\Enums\MessageKind::System) bg-zinc-100 dark:bg-zinc-800 @endif">
                <div class="flex items-start justify-between">
                    <div class="flex items-start gap-3">
                        <flux:avatar size="sm" :src="null" initials="{{ $message->user->initials() }}" />
                        <div>
                            <div class="font-semibold">{{ $message->user->name }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $message->created_at->format('M j, Y g:i A') }}
                                @if($message->kind === \App\Enums\MessageKind::InternalNote)
                                    <flux:badge size="sm" color="amber">Internal Note</flux:badge>
                                @elseif($message->kind === \App\Enums\MessageKind::System)
                                    <flux:badge size="sm" color="zinc">System</flux:badge>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($message->kind === \App\Enums\MessageKind::Message && auth()->user()->can('flag', $message))
                        <flux:button wire:click="openFlagModal({{ $message->id }})" variant="ghost" size="sm">
                            <flux:icon.flag class="size-4" />
                        </flux:button>
                    @endif
                </div>

                <div class="mt-3 prose prose-sm dark:prose-invert max-w-none">
                    {!! nl2br(e($message->body)) !!}
                </div>

                {{-- Show flags for staff with viewFlagged permission --}}
                @if($this->canViewFlagged && $message->flags->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach($message->flags as $flag)
                            <div class="rounded border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950 p-3">
                                <div class="flex items-start justify-between">
                                    <div class="text-sm">
                                        <strong>Flagged by {{ $flag->flaggedBy->name }}</strong> on {{ $flag->created_at->format('M j, Y g:i A') }}
                                        <div class="mt-1 text-zinc-700 dark:text-zinc-300">{{ $flag->note }}</div>
                                        @if($flag->status->value === 'acknowledged')
                                            <div class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">
                                                <strong>Acknowledged by {{ $flag->reviewedBy->name }}</strong> on {{ $flag->reviewed_at->format('M j, Y g:i A') }}
                                                @if($flag->staff_notes)
                                                    <div class="mt-1">{{ $flag->staff_notes }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    @if($flag->status->value === 'new')
                                        <flux:button wire:click="openAcknowledgeModal({{ $flag->id }})" variant="primary" size="sm">
                                            Acknowledge Flag
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Reply Form --}}
    @if($this->canReply)
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <form wire:submit="sendReply">
                <flux:field>
                    <flux:label>Reply</flux:label>
                    <flux:textarea wire:model="replyMessage" rows="4" placeholder="Type your reply..." />
                    <flux:error name="replyMessage" />
                </flux:field>

                <div class="mt-4 flex items-center justify-between">
                    <div>
                        @if($this->canAddInternalNotes)
                            <flux:checkbox wire:model="isInternalNote" label="Internal Note (Staff Only)" />
                        @endif
                    </div>
                    <flux:button type="submit" variant="primary">Send Reply</flux:button>
                </div>
            </form>
        </div>
    @endif

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

        <div class="flex justify-end gap-2">
            <flux:button wire:click="$dispatch('close')" variant="ghost">Cancel</flux:button>
            <flux:button wire:click="submitFlag" variant="danger">Submit Flag</flux:button>
        </div>
    </flux:modal>

    {{-- Acknowledge Flag Modal --}}
    <flux:modal name="acknowledge-flag" class="space-y-6">
        <div>
            <flux:heading size="lg">Acknowledge Flag</flux:heading>
            <flux:subheading>Add notes about your review of this flag (optional)</flux:subheading>
        </div>

        <flux:field>
            <flux:label>Staff Notes</flux:label>
            <flux:textarea wire:model="staffNotes" rows="4" placeholder="Add any notes about your review of this flag..." />
        </flux:field>

        <div class="flex justify-end gap-2">
            <flux:button wire:click="$dispatch('close')" variant="ghost">Cancel</flux:button>
            <flux:button wire:click="acknowledgeFlag" variant="primary">Acknowledge Flag</flux:button>
        </div>
    </flux:modal>
</div>
