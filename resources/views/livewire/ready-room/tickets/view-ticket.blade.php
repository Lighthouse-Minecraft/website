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
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    public Thread $thread;

    #[Url]
    public ?string $filter = null;

    public string $replyMessage = '';

    public bool $isInternalNote = false;

    public ?int $flaggingMessageId = null;

    public string $flagReason = '';

    public ?int $acknowledgingFlagId = null;

    public string $staffNotes = '';

    /**
     * Initialize the component for the given thread: authorize access, attach the thread to the component,
     * register the current user as a viewer for read-tracking, and update the participant's read timestamp when present.
     *
     * @param  \App\Models\Thread  $thread  The thread (ticket) to mount into the component.
     */
    public function mount(Thread $thread): void
    {
        $this->authorize('view', $thread);
        $this->thread = $thread;

        // Add viewer (not participant) so we can track their read status
        // Viewers can see the ticket but won't get notifications for new messages
        $this->thread->addViewer(auth()->user());

        // Mark thread as read for this user
        $participant = $this->thread->participants()
            ->where('user_id', auth()->id())
            ->first();

        if ($participant) {
            $participant->update(['last_read_at' => now()]);
            // Clear caches so counts update immediately
            auth()->user()->clearTicketCaches();
        }
    }

    #[Computed]
    public function messages()
    {
        $messages = $this->thread->messages()
            ->with(['user.minecraftAccounts', 'user.discordAccounts', 'flags.flaggedBy', 'flags.reviewedBy'])
            ->orderBy('created_at')
            ->get();

        // Filter out internal notes for non-staff
        if (! auth()->user()->can('internalNotes', $this->thread)) {
            $messages = $messages->filter(fn ($msg) => $msg->kind !== MessageKind::InternalNote);
        }

        return $messages;
    }

    #[Computed]
    public function canReply(): bool
    {
        // Can't reply to closed tickets
        if ($this->thread->status === ThreadStatus::Closed) {
            return false;
        }

        // Non-staff can't reply to resolved tickets
        if ($this->thread->status === ThreadStatus::Resolved && ! auth()->user()->isAtLeastRank(\App\Enums\StaffRank::CrewMember)) {
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
    public function backUrl(): string
    {
        if ($this->filter) {
            return '/tickets?filter=' . urlencode($this->filter);
        }
        return '/tickets';
    }

    #[Computed]
    public function canViewFlagged(): bool
    {
        return auth()->user()->can('viewFlagged', Thread::class);
    }

    #[Computed]
    public function canClose(): bool
    {
        return auth()->user()->can('close', $this->thread)
            && $this->thread->status !== ThreadStatus::Closed;
    }

    #[Computed]
    public function targetUser()
    {
        // For admin tickets, find the participant who is NOT the creator
        if ($this->thread->subtype === \App\Enums\ThreadSubtype::AdminAction) {
            $participant = $this->thread->participants()
                ->where('user_id', '!=', $this->thread->created_by_user_id)
                ->with('user')
                ->first();

            return $participant?->user ?? $this->thread->createdBy;
        }

        // For regular tickets, it's the creator
        return $this->thread->createdBy;
    }

    /**
     * Retrieve staff users who have both a staff rank and a staff department, ordered by department, rank, then name.
     *
     * @return \Illuminate\Database\Eloquent\Collection|\App\Models\User[] Collection of User models matching the staff criteria, ordered by `staff_department`, `staff_rank`, then `name`.
     */
    #[Computed]
    public function staffUsers()
    {
        return User::whereNotNull('staff_rank')
            ->whereNotNull('staff_department')
            ->orderBy('staff_department')
            ->orderBy('staff_rank')
            ->orderBy('name')
            ->get();
    }

    /**
     * Process a reply by creating a message and performing related updates.
     *
     * @param string $body The message body text
     * @param bool $isInternal Whether this is an internal note
     * @return void
     */
    private function processReply(string $body, bool $isInternal): void
    {
        $this->authorize('reply', $this->thread);

        $kind = MessageKind::Message;

        if ($isInternal) {
            $this->authorize('internalNotes', $this->thread);
            $kind = MessageKind::InternalNote;
        }

        // Capture a single timestamp for consistency
        $now = now();

        $message = Message::create([
            'thread_id' => $this->thread->id,
            'user_id' => auth()->id(),
            'body' => $body,
            'kind' => $kind,
        ]);

        // Add sender as participant (not viewer) if not already
        $existingParticipant = $this->thread->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (! $existingParticipant) {
            $this->thread->addParticipant(auth()->user(), isViewer: false);
            $existingParticipant = $this->thread->participants()
                ->where('user_id', auth()->id())
                ->first();
            // Log when a user joins a ticket
            \App\Actions\RecordActivity::run($this->thread, 'ticket_joined', 'Joined ticket: ' . $this->thread->subject);
        } elseif ($existingParticipant->is_viewer) {
            $existingParticipant->update(['is_viewer' => false]);
            // Log when a viewer becomes a participant
            \App\Actions\RecordActivity::run($this->thread, 'ticket_joined', 'Joined ticket: ' . $this->thread->subject);
        }

        // Mark as read for the sender
        $existingParticipant->update(['last_read_at' => $now]);

        // Update thread last message time
        $this->thread->update(['last_message_at' => $now]);

        // Auto-assign unassigned tickets when staff replies (atomic to prevent race)
        if (! $this->thread->assigned_to_user_id
            && auth()->id() !== $this->thread->created_by_user_id
            && auth()->user()->isAtLeastRank(\App\Enums\StaffRank::CrewMember)
            && ! $isInternal) {
            $affected = \App\Models\Thread::where('id', $this->thread->id)
                ->whereNull('assigned_to_user_id')
                ->update(['assigned_to_user_id' => auth()->id()]);

            if ($affected > 0) {
                $this->thread->refresh();
                \App\Actions\RecordActivity::run(
                    $this->thread,
                    'assignment_changed',
                    'Auto-assigned to ' . auth()->user()->name . ' on first reply'
                );
            }
        }

        // Notify participants (except sender, viewers, and for internal notes)
        if ($kind !== MessageKind::InternalNote) {
            $participants = $this->thread->participants()
                ->where('user_id', '!=', auth()->id())
                ->where('is_viewer', false)
                ->with('user')
                ->get();

            $notificationService = app(TicketNotificationService::class);
            foreach ($participants as $participant) {
                $notificationService->send($participant->user, new NewTicketReplyNotification($message));
            }
        }

        // Clear ticket caches for all participants
        $allParticipants = $this->thread->participants()->with('user')->get();
        foreach ($allParticipants as $participant) {
            $participant->user->clearTicketCaches();
        }
    }

    /**
     * Sends the current reply for the thread, creating a message or internal note and performing related updates.
     *
     * Validates the reply text and authorizes the action (including internal-note permission when requested). Creates a Message on the thread (marked as an internal note when selected), ensures the sender is recorded as a non-viewer participant and updates their read timestamp, updates the thread's last message time, and records activity. For normal replies (not internal notes) notifies other non-viewer participants. Resets reply-related state, shows a success toast, and clears the cached messages list.
     */
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

        $this->processReply($this->replyMessage, $this->isInternalNote);

        $this->replyMessage = '';
        $this->isInternalNote = false;

        Flux::toast('Reply sent successfully!', variant: 'success');

        unset($this->messages);
    }

    public function changeStatus(?string $newStatus): void
    {
        if (! $newStatus) {
            return;
        }

        $this->authorize('changeStatus', $this->thread);

        // Validate that the status is a valid ThreadStatus value
        $status = ThreadStatus::tryFrom($newStatus);
        if (! $status) {
            $this->addError('status', 'Invalid status value.');

            return;
        }

        $oldStatus = $this->thread->status;
        $this->thread->update(['status' => $status]);

        \App\Actions\RecordActivity::run(
            $this->thread,
            'status_changed',
            "Status changed: {$oldStatus->label()} → {$this->thread->status->label()}"
        );

        // Clear ticket caches for all participants
        $allParticipants = $this->thread->participants()->with('user')->get();
        foreach ($allParticipants as $participant) {
            $participant->user->clearTicketCaches();
        }

        Flux::toast('Status updated successfully!', variant: 'success');
    }

    /**
     * Assigns the thread to a staff user or removes the current assignment.
     *
     * Authorizes the action, then if `$userId` is `null` unassigns the thread; otherwise validates the target exists and is a staff member, updates the thread's assignee, records an `assignment_changed` activity, and notifies the new assignee and the thread creator (if different). Validation failures add field errors and abort assignment without performing changes.
     *
     * @param  int|null  $userId  The ID of the staff user to assign the thread to, or `null` to unassign.
     */
    public function assignTo(?int $userId): void
    {
        $this->authorize('assign', $this->thread);

        // Allow unassigning (setting to null)
        if ($userId === null) {
            $oldAssignee = $this->thread->assignedTo;
            $this->thread->update(['assigned_to_user_id' => null]);

            \App\Actions\RecordActivity::run(
                $this->thread,
                'assignment_changed',
                $oldAssignee ? "Assignment removed: {$oldAssignee->name} → Unassigned" : 'Unassigned'
            );

            // Clear ticket caches for all participants
            $allParticipants = $this->thread->participants()->with('user')->get();
            foreach ($allParticipants as $participant) {
                $participant->user->clearTicketCaches();
            }
            // Also clear for old assignee if they existed
            if ($oldAssignee) {
                $oldAssignee->clearTicketCaches();
            }

            Flux::toast('Ticket unassigned successfully!', variant: 'success');

            return;
        }

        // Validate the user exists
        $newAssignee = User::find($userId);
        if (! $newAssignee) {
            $this->addError('assignee', 'Invalid user selected.');

            return;
        }

        // Validate the user is staff
        if (! $newAssignee->staff_rank) {
            $this->addError('assignee', 'Only staff members can be assigned to tickets.');

            return;
        }

        $oldAssignee = $this->thread->assignedTo;
        $this->thread->update(['assigned_to_user_id' => $userId]);

        $description = $oldAssignee
            ? "Assignment changed: {$oldAssignee->name} → {$newAssignee->name}"
            : "Assigned to: {$newAssignee->name}";

        \App\Actions\RecordActivity::run($this->thread, 'assignment_changed', $description);

        // Notify the new assignee (skip if assigning to yourself) and the ticket creator
        $notificationService = app(TicketNotificationService::class);
        if ($newAssignee->id !== auth()->id()) {
            $notificationService->send($newAssignee, new TicketAssignedNotification($this->thread));
        }

        if ($this->thread->createdBy && $this->thread->createdBy->id !== $newAssignee->id) {
            $notificationService->send($this->thread->createdBy, new TicketAssignedNotification($this->thread));
        }

        // Clear ticket caches for all participants and assignees
        $allParticipants = $this->thread->participants()->with('user')->get();
        foreach ($allParticipants as $participant) {
            $participant->user->clearTicketCaches();
        }
        // Also clear for old and new assignees if they exist
        if ($oldAssignee) {
            $oldAssignee->clearTicketCaches();
        }
        $newAssignee->clearTicketCaches();

        Flux::toast('Ticket assigned successfully!', variant: 'success');
    }

    public function closeTicket(): void
    {
        $this->authorize('close', $this->thread);

        // If there's a reply message, send it first
        if (! empty(trim($this->replyMessage))) {
            $this->processReply($this->replyMessage, $this->isInternalNote);

            $this->replyMessage = '';
            $this->isInternalNote = false;
        }

        $oldStatus = $this->thread->status;

        // Staff can close directly, regular users mark as resolved
        $isStaff = auth()->user()->isAtLeastRank(\App\Enums\StaffRank::CrewMember);
        $newStatus = $isStaff ? ThreadStatus::Closed : ThreadStatus::Resolved;

        $this->thread->update(['status' => $newStatus]);

        $systemUser = User::where('email', 'system@lighthouse.local')->firstOrFail();

        // Create system message
        $systemMessageBody = $isStaff
            ? auth()->user()->name.' closed this ticket.'
            : auth()->user()->name.' marked this ticket as resolved.';

        Message::create([
            'thread_id' => $this->thread->id,
            'user_id' => $systemUser->id,
            'body' => $systemMessageBody,
            'kind' => MessageKind::System,
        ]);

        $this->thread->update(['last_message_at' => now()]);

        \App\Actions\RecordActivity::run(
            $this->thread,
            'status_changed',
            "Status changed: {$oldStatus->label()} → {$newStatus->label()}"
        );

        // Clear ticket caches for all participants
        $allParticipants = $this->thread->participants()->with('user')->get();
        foreach ($allParticipants as $participant) {
            $participant->user->clearTicketCaches();
        }

        $toastMessage = $isStaff ? 'Ticket closed successfully!' : 'Ticket marked as resolved!';
        Flux::toast($toastMessage, variant: 'success');

        unset($this->messages);
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
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                <span>For: <a href="{{ route('profile.show', $this->targetUser) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $this->targetUser->name }}</a></span>
                <span>•</span>
                <span>Department: {{ $thread->department->label() }}</span>
                <span>•</span>
                <span>Ticket Type: {{ $thread->subtype->label() }}</span>
                <span>•</span>
                <span>Status: {{ $thread->status->label() }}</span>
                <span>•</span>
                <span>Created {{ $thread->created_at->diffForHumans() }}</span>
            </div>
        </div>
        <flux:button :href="$this->backUrl" variant="ghost" size="sm">← Back to Tickets</flux:button>
    </div>

    {{-- Status & Assignment Controls (Staff Only) --}}
    @if($this->canChangeStatus || $this->canAssign)
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4">
            @if($this->canChangeStatus)
                <flux:field class="flex-1">
                    <flux:label>Status</flux:label>
                    <flux:select wire:change="changeStatus($event.target.value)" variant="listbox">
                        <flux:select.option value="open" :selected="$thread->status->value === 'open'">Open</flux:select.option>
                        <flux:select.option value="pending" :selected="$thread->status->value === 'pending'">Pending</flux:select.option>
                        <flux:select.option value="resolved" :selected="$thread->status->value === 'resolved'">Resolved</flux:select.option>
                        <flux:select.option value="closed" :selected="$thread->status->value === 'closed'">Closed</flux:select.option>
                    </flux:select>
                </flux:field>
            @endif

            @if($this->canAssign)
                <flux:field class="flex-1">
                    <flux:label>Assigned To</flux:label>
                    <flux:select wire:change="assignTo($event.target.value ? parseInt($event.target.value) : null)" variant="listbox">
                        <flux:select.option value="" :selected="!$thread->assigned_to_user_id">Unassigned</flux:select.option>
                        @foreach($this->staffUsers as $staff)
                            <flux:select.option value="{{ $staff->id }}" :selected="$thread->assigned_to_user_id == $staff->id">
                                {{ $staff->name }} ({{ $staff->staff_department?->label() }} - {{ $staff->staff_rank?->label() }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            @endif
        </div>
    @endif

    {{-- Messages --}}
    <div class="space-y-4">
        @php $tz = auth()->user()->timezone ?? 'UTC'; @endphp
        @foreach($this->messages as $message)
            <div wire:key="message-{{ $message->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 @if($message->kind === \App\Enums\MessageKind::InternalNote) bg-amber-50/50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800 @elseif($message->kind === \App\Enums\MessageKind::System) bg-zinc-100 dark:bg-zinc-800 @endif">
                <div class="flex items-start justify-between">
                    <div class="flex items-start gap-3">
                        <flux:avatar size="sm" :src="$message->user->avatarUrl()" initials="{{ $message->user->initials() }}" />
                        <div>
                            <div class="font-semibold"><a href="{{ route('profile.show', $message->user) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $message->user->name }}</a></div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $message->created_at->setTimezone($tz)->format('M j, Y g:i A') }}
                                @if($message->kind === \App\Enums\MessageKind::InternalNote)
                                    <flux:badge size="sm" color="amber">Internal Note</flux:badge>
                                @elseif($message->kind === \App\Enums\MessageKind::System)
                                    <flux:badge size="sm" color="zinc">System</flux:badge>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($message->kind === \App\Enums\MessageKind::Message && $message->user_id !== auth()->id() && auth()->user()->can('flag', $message))
                        <flux:button wire:click="openFlagModal({{ $message->id }})" variant="ghost" size="sm">
                            <flux:icon.flag class="size-4" />
                        </flux:button>
                    @endif
                </div>

                <div class="mt-3 prose prose-sm dark:prose-invert max-w-none [&_a]:text-blue-600 dark:[&_a]:text-blue-400 [&_a]:underline [&_a]:font-medium hover:[&_a]:text-blue-700 dark:hover:[&_a]:text-blue-300">
                    {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>

                {{-- Show flags for staff with viewFlagged permission --}}
                @if($this->canViewFlagged && $message->flags->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach($message->flags as $flag)
                            <div wire:key="flag-{{ $flag->id }}" class="rounded border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950 p-3">
                                <div class="flex items-start justify-between">
                                    <div class="text-sm">
                                        <strong>Flagged by <a href="{{ route('profile.show', $flag->flaggedBy) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $flag->flaggedBy->name }}</a></strong> on {{ $flag->created_at->setTimezone($tz)->format('M j, Y g:i A') }}
                                        <div class="mt-1 text-zinc-700 dark:text-zinc-300">{{ $flag->note }}</div>
                                        @if($flag->status->value === 'acknowledged')
                                            <div class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">
                                                @if($flag->reviewedBy && $flag->reviewed_at)
                                                    <strong>Acknowledged by <a href="{{ route('profile.show', $flag->reviewedBy) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $flag->reviewedBy->name }}</a></strong> on {{ $flag->reviewed_at->setTimezone($tz)->format('M j, Y g:i A') }}
                                                @else
                                                    <strong>Acknowledged</strong>
                                                @endif
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

                <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        @if($this->canAddInternalNotes)
                            <flux:checkbox wire:model="isInternalNote" label="Internal Note (Staff Only)" />
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if($this->canClose)
                            <flux:button wire:click="closeTicket" variant="filled">Close Ticket</flux:button>
                        @endif
                        <flux:button type="submit" variant="primary">Send Reply</flux:button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- Back to Tickets Button (Bottom) --}}
    <div class="flex justify-end">
        <flux:button :href="$this->backUrl" variant="ghost" size="sm">← Back to Tickets</flux:button>
    </div>

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
