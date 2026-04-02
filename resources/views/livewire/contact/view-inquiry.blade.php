<?php

use App\Actions\RecordActivity;
use App\Actions\SendGuestConversationEmail;
use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use App\Services\TicketNotificationService;
use App\Notifications\NewContactInquiryNotification;
use Flux\Flux;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public Thread $thread;

    public string $replyBody = '';
    public bool $isInternalNote = false;
    public bool $emailGuest = true;
    public string $newStatus = '';

    public function mount(Thread $thread): void
    {
        $this->authorize('view-contact-inquiries');

        if ($thread->type !== ThreadType::ContactInquiry) {
            abort(404);
        }

        $this->thread = $thread;
        $this->newStatus = $thread->status->value;

        // Mark as read for the current user
        $participant = $this->thread->participants()
            ->where('user_id', auth()->id())
            ->first();

        if ($participant) {
            $participant->update(['last_read_at' => now()]);
        }
    }

    #[Computed]
    public function threadMessages()
    {
        return $this->thread->messages()
            ->with('user')
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function category(): string
    {
        if (preg_match('/^\[([^\]]+)\]/', $this->thread->subject, $matches)) {
            return $matches[1];
        }

        return '';
    }

    #[Computed]
    public function subjectText(): string
    {
        return preg_replace('/^\[[^\]]+\]\s*/', '', $this->thread->subject);
    }

    #[Computed]
    public function canReply(): bool
    {
        return $this->thread->status !== ThreadStatus::Closed
            && $this->thread->status !== ThreadStatus::Resolved;
    }

    public function sendReply(): void
    {
        $this->authorize('view-contact-inquiries');

        $this->validate([
            'replyBody' => ['required', 'string', 'min:1'],
        ]);

        $kind = $this->isInternalNote ? MessageKind::InternalNote : MessageKind::Message;

        $message = Message::create([
            'thread_id' => $this->thread->id,
            'user_id' => auth()->id(),
            'body' => $this->replyBody,
            'kind' => $kind,
            'guest_email_sent' => false,
        ]);

        // Email guest if this is a reply (not internal note) with email toggle ON
        if (! $this->isInternalNote && $this->emailGuest) {
            SendGuestConversationEmail::run($this->thread, $message);
        }

        // Update thread last message time
        $now = now();
        $this->thread->update(['last_message_at' => $now]);

        // Mark as read for the sender
        $this->thread->participants()
            ->where('user_id', auth()->id())
            ->update(['last_read_at' => $now]);

        // Notify other participants (not for internal notes)
        if (! $this->isInternalNote) {
            $participants = $this->thread->participants()
                ->where('user_id', '!=', auth()->id())
                ->where('is_viewer', false)
                ->with('user')
                ->get();

            $notificationService = app(TicketNotificationService::class);
            foreach ($participants as $participant) {
                if (! $participant->user) {
                    continue;
                }
                $notificationService->send(
                    $participant->user,
                    new NewContactInquiryNotification($this->thread),
                    'staff_alerts'
                );
            }
        }

        RecordActivity::run(
            $this->thread,
            $this->isInternalNote ? 'internal_note_added' : 'reply_sent',
            $this->isInternalNote ? 'Internal note added.' : 'Staff reply sent.'
        );

        $this->replyBody = '';
        $this->isInternalNote = false;
        $this->emailGuest = true;

        unset($this->threadMessages);

        Flux::toast('Reply sent successfully!', variant: 'success');
    }

    public function changeStatus(): void
    {
        $this->authorize('view-contact-inquiries');

        $status = ThreadStatus::tryFrom($this->newStatus);
        if (! $status) {
            return;
        }

        $oldStatus = $this->thread->status;
        $updateData = ['status' => $status];

        if ($status === ThreadStatus::Closed) {
            $updateData['closed_at'] = now();
        } elseif ($oldStatus === ThreadStatus::Closed) {
            $updateData['closed_at'] = null;
        }

        $this->thread->update($updateData);

        RecordActivity::run(
            $this->thread,
            'status_changed',
            "Status changed: {$oldStatus->label()} → {$status->label()}"
        );

        Flux::toast('Status updated.', variant: 'success');
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ $this->subjectText }}</flux:heading>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                @if($this->category)
                    <span>Category: {{ $this->category }}</span>
                    <span>•</span>
                @endif
                <span>From: {{ $thread->guest_name ?? 'Anonymous' }}</span>
                <span>•</span>
                <span>Email: {{ $thread->guest_email }}</span>
                <span>•</span>
                <span>Status: {{ $thread->status->label() }}</span>
                <span>•</span>
                <span>{{ $thread->created_at->diffForHumans() }}</span>
            </div>
        </div>
        <flux:button href="{{ route('contact-inquiries.index') }}" variant="ghost" size="sm" wire:navigate>← Back to Inquiries</flux:button>
    </div>

    {{-- Status Management --}}
    <div class="flex items-center gap-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4 mb-6">
        <flux:field class="flex-1">
            <flux:label>Status</flux:label>
            <flux:select wire:model="newStatus" variant="listbox">
                <flux:select.option value="open">Open</flux:select.option>
                <flux:select.option value="pending">Pending</flux:select.option>
                <flux:select.option value="resolved">Resolved</flux:select.option>
                <flux:select.option value="closed">Closed</flux:select.option>
            </flux:select>
        </flux:field>
        <flux:button wire:click="changeStatus" variant="ghost" size="sm" class="mt-6">Update Status</flux:button>
    </div>

    {{-- Messages --}}
    <div class="flex flex-col gap-4 mb-6">
        @foreach($this->threadMessages as $message)
            @if($message->kind === MessageKind::InternalNote)
                {{-- Internal Note --}}
                <div wire:key="msg-{{ $message->id }}" class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <flux:badge color="amber" size="sm">Internal Note</flux:badge>
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $message->user?->name ?? 'Staff' }}</span>
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                </div>
            @elseif($message->user_id)
                {{-- Staff reply --}}
                <div wire:key="msg-{{ $message->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $message->user?->name ?? 'Staff' }}</span>
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->diffForHumans() }}</span>
                        @if($message->guest_email_sent)
                            <flux:badge color="blue" size="sm" icon="envelope">Emailed to guest</flux:badge>
                        @endif
                    </div>
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                </div>
            @else
                {{-- Guest message --}}
                <div wire:key="msg-{{ $message->id }}" class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <flux:badge color="blue" size="sm">Guest</flux:badge>
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $thread->guest_name ?? 'Anonymous' }}</span>
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Reply Composer --}}
    @if($this->canReply)
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex items-center gap-4 mb-4">
                <flux:radio.group wire:model.live="isInternalNote" label="Message type" variant="segmented">
                    <flux:radio :value="false" label="Reply" />
                    <flux:radio :value="true" label="Internal Note" />
                </flux:radio.group>

                @if(! $isInternalNote)
                    <div class="flex items-center gap-2">
                        <flux:checkbox wire:model="emailGuest" label="Email guest" />
                    </div>
                @endif
            </div>

            <flux:textarea
                wire:model="replyBody"
                rows="4"
                placeholder="{{ $isInternalNote ? 'Add an internal note (not visible to guest)...' : 'Type your reply to the guest...' }}"
            />
            @error('replyBody') <flux:error>{{ $message }}</flux:error> @enderror

            <div class="mt-3 flex justify-end">
                <flux:button wire:click="sendReply" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ $isInternalNote ? 'Add Note' : 'Send Reply' }}</span>
                    <span wire:loading>Sending...</span>
                </flux:button>
            </div>
        </div>
    @else
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 text-center text-sm text-zinc-500 dark:text-zinc-400">
            This inquiry is {{ $thread->status->label() }}. Change the status to reply.
        </div>
    @endif
</div>
