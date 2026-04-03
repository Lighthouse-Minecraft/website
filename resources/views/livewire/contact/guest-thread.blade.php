<?php

use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use App\Notifications\NewContactInquiryNotification;
use App\Services\TicketNotificationService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Thread $thread;
    public string $replyBody = '';

    public function mount(string $token): void
    {
        $thread = Thread::where('conversation_token', $token)
            ->where('type', ThreadType::ContactInquiry)
            ->first();

        if (! $thread) {
            abort(404);
        }

        $this->thread = $thread;
    }

    #[Computed]
    public function threadMessages()
    {
        return $this->thread->messages()
            ->with('user')
            ->where('kind', '!=', MessageKind::InternalNote->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function subjectText(): string
    {
        return preg_replace('/^\[[^\]]+\]\s*/', '', $this->thread->subject);
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
    public function canReply(): bool
    {
        return $this->thread->status === ThreadStatus::Open
            || $this->thread->status === ThreadStatus::Pending;
    }

    public function submitReply(): void
    {
        $this->thread->refresh();

        if (! in_array($this->thread->status, [ThreadStatus::Open, ThreadStatus::Pending], true)) {
            Flux::toast('This conversation has already been closed.', variant: 'danger');

            return;
        }

        $this->validate([
            'replyBody' => ['required', 'string', 'min:1'],
        ]);

        Message::create([
            'thread_id' => $this->thread->id,
            'body' => $this->replyBody,
            'kind' => MessageKind::Message,
            'guest_email_sent' => false,
        ]);

        $now = now();
        $this->thread->update(['last_message_at' => $now]);

        // Notify all non-viewer participants
        $participants = $this->thread->participants()
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

        $this->replyBody = '';
        unset($this->threadMessages);

        Flux::toast('Your reply has been sent.', variant: 'success');
    }
}; ?>

<div class="max-w-3xl mx-auto py-8 px-4">
    {{-- Thread header --}}
    <div class="mb-6">
        <flux:heading size="xl">{{ $this->subjectText }}</flux:heading>
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
            @if($this->category)
                <span>{{ $this->category }}</span>
                <span>•</span>
            @endif
            <span>{{ $thread->created_at->format('M j, Y') }}</span>
        </div>
    </div>

    {{-- Closed/Resolved banner --}}
    @if(! $this->canReply)
        <div class="mb-6 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-zinc-100 dark:bg-zinc-800 px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
            This conversation has been closed. If you need further assistance, please <a href="/contact" class="text-blue-600 dark:text-blue-400 underline">submit a new inquiry</a>.
        </div>
    @endif

    {{-- Messages --}}
    <div class="flex flex-col gap-4 mb-6">
        @foreach($this->threadMessages as $msg)
            @if($msg->user_id)
                {{-- Staff reply --}}
                <div wire:key="msg-{{ $msg->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $msg->user?->name ?? 'Staff' }}</span>
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $msg->created_at->format('M j, Y g:i A') }}</span>
                    </div>
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! Str::markdown($msg->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                </div>
            @else
                {{-- Guest message --}}
                <div wire:key="msg-{{ $msg->id }}" class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $thread->guest_name ?? 'You' }}</span>
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $msg->created_at->format('M j, Y g:i A') }}</span>
                    </div>
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! Str::markdown($msg->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Reply form --}}
    @if($this->canReply)
        <flux:card>
            <flux:heading size="sm">Send a Reply</flux:heading>
            <div class="mt-3">
                <flux:textarea
                    wire:model="replyBody"
                    rows="4"
                    placeholder="Type your reply..."
                />
                @error('replyBody') <flux:error>{{ $message }}</flux:error> @enderror
            </div>
            <div class="mt-3 flex justify-end">
                <flux:button wire:click="submitReply" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Send Reply</span>
                    <span wire:loading>Sending...</span>
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
