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

        $this->thread->refresh();

        if (! $this->canReply) {
            Flux::toast('This inquiry is already closed.', variant: 'danger');

            return;
        }

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
        <flux:button href="{{ route('discussions.index', ['filter' => 'contact']) }}" variant="ghost" size="sm" wire:navigate>← Back to Inquiries</flux:button>
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
    <div class="mx-auto w-full max-w-3xl mb-6">
    <div class="flex flex-col gap-4">
        @php $tz = auth()->user()->timezone ?? 'UTC'; @endphp
        @foreach($this->threadMessages as $message)
            @php $isOwn = $message->user_id === auth()->id(); @endphp

            @if($message->kind === MessageKind::InternalNote)
                {{-- Internal Note — always left-aligned, amber --}}
                <div wire:key="msg-{{ $message->id }}" class="chat-message chat-message-start">
                    <flux:avatar size="sm" :src="$message->user?->avatarUrl()" :initials="$message->user?->initials() ?? 'S'" class="shrink-0 mt-1" />
                    <div class="min-w-0">
                        <div class="flex items-baseline gap-2 mb-1">
                            <span class="font-semibold text-sm text-zinc-700 dark:text-zinc-300">{{ $message->user?->name ?? 'Staff' }}</span>
                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->setTimezone($tz)->format('M j, Y g:i A') }}</span>
                            <flux:badge size="sm" color="amber">Internal Note</flux:badge>
                        </div>
                        <div class="chat-bubble chat-bubble-start border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30">
                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </div>
                    </div>
                </div>

            @elseif($message->user_id && $isOwn)
                {{-- Own staff reply — right-aligned, cyan --}}
                <div wire:key="msg-{{ $message->id }}" class="chat-message chat-message-end">
                    <flux:avatar size="sm" :src="$message->user?->avatarUrl()" :initials="$message->user?->initials() ?? 'S'" class="shrink-0 mt-1" />
                    <div class="min-w-0">
                        <div class="flex items-baseline gap-2 mb-1 justify-end">
                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->setTimezone($tz)->format('M j, Y g:i A') }}</span>
                            @if($message->guest_email_sent)
                                <flux:badge color="blue" size="sm" icon="envelope">Emailed to guest</flux:badge>
                            @endif
                        </div>
                        <div class="chat-bubble chat-bubble-end bg-cyan-50 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800">
                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </div>
                    </div>
                </div>

            @elseif($message->user_id)
                {{-- Other staff reply — left-aligned, neutral --}}
                <div wire:key="msg-{{ $message->id }}" class="chat-message chat-message-start">
                    <flux:avatar size="sm" :src="$message->user?->avatarUrl()" :initials="$message->user?->initials() ?? 'S'" class="shrink-0 mt-1" />
                    <div class="min-w-0">
                        <div class="flex items-baseline gap-2 mb-1">
                            <span class="font-semibold text-sm text-blue-600 dark:text-blue-400">{{ $message->user?->name ?? 'Staff' }}</span>
                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->setTimezone($tz)->format('M j, Y g:i A') }}</span>
                            @if($message->guest_email_sent)
                                <flux:badge color="blue" size="sm" icon="envelope">Emailed to guest</flux:badge>
                            @endif
                        </div>
                        <div class="chat-bubble chat-bubble-start bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </div>
                    </div>
                </div>

            @else
                {{-- Guest message — left-aligned, blue --}}
                <div wire:key="msg-{{ $message->id }}" class="chat-message chat-message-start">
                    <flux:avatar size="sm" :initials="Str::upper(Str::substr($thread->guest_name ?? 'G', 0, 1))" class="shrink-0 mt-1" />
                    <div class="min-w-0">
                        <div class="flex items-baseline gap-2 mb-1">
                            <span class="font-semibold text-sm text-zinc-700 dark:text-zinc-300">{{ $thread->guest_name ?? 'Guest' }}</span>
                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->setTimezone($tz)->format('M j, Y g:i A') }}</span>
                            <flux:badge size="sm" color="blue">Guest</flux:badge>
                        </div>
                        <div class="chat-bubble chat-bubble-start bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800">
                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                {!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
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
