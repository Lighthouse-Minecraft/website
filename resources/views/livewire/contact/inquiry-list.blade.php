<?php

use App\Enums\ThreadType;
use App\Models\Thread;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url]
    public string $filter = 'open';

    public function mount(): void
    {
        $this->authorize('view-contact-inquiries');
    }

    #[Computed]
    public function inquiries()
    {
        $query = Thread::with(['participants' => function ($q) {
            $q->where('user_id', auth()->id());
        }])
            ->where('type', ThreadType::ContactInquiry)
            ->orderBy('last_message_at', 'desc');

        match ($this->filter) {
            'open' => $query->where('status', '!=', 'closed'),
            'closed' => $query->where('status', 'closed'),
            default => $query->where('status', '!=', 'closed'),
        };

        return $query->get();
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

    public function category(Thread $thread): string
    {
        // Category is stored as the prefix of the subject: "[Category] Subject"
        if (preg_match('/^\[([^\]]+)\]/', $thread->subject, $matches)) {
            return $matches[1];
        }

        return '';
    }

    public function subject(Thread $thread): string
    {
        // Strip the category prefix from the subject
        return preg_replace('/^\[[^\]]+\]\s*/', '', $thread->subject);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Contact Inquiries</flux:heading>
    </div>

    {{-- Filters --}}
    <div class="mb-6 flex items-center gap-2">
        <flux:button
            wire:click="$set('filter', 'open')"
            variant="{{ $filter === 'open' ? 'primary' : 'ghost' }}"
            size="sm"
        >
            Open
        </flux:button>
        <flux:button
            wire:click="$set('filter', 'closed')"
            variant="{{ $filter === 'closed' ? 'primary' : 'ghost' }}"
            size="sm"
        >
            Closed
        </flux:button>
    </div>

    {{-- Inquiry List --}}
    @if($this->inquiries->isNotEmpty())
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach($this->inquiries as $inquiry)
                <a
                    href="/contact-inquiries/{{ $inquiry->id }}"
                    wire:navigate
                    wire:key="inquiry-{{ $inquiry->id }}"
                    class="block p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <flux:heading size="sm">{{ $this->subject($inquiry) }}</flux:heading>
                                @if($this->category($inquiry))
                                    <flux:badge size="sm" variant="outline">{{ $this->category($inquiry) }}</flux:badge>
                                @endif
                                @if($this->isUnread($inquiry))
                                    <flux:badge color="blue" size="sm">New</flux:badge>
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                <span>{{ $inquiry->guest_name ?? 'Anonymous' }}</span>
                                <span class="mx-2">•</span>
                                <span>{{ $inquiry->guest_email }}</span>
                                <span class="mx-2">•</span>
                                <span>{{ $inquiry->last_message_at?->diffForHumans() ?? $inquiry->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge
                                color="{{ $inquiry->status === \App\Enums\ThreadStatus::Open ? 'green' : ($inquiry->status === \App\Enums\ThreadStatus::Pending ? 'amber' : 'zinc') }}"
                            >
                                {{ $inquiry->status->label() }}
                            </flux:badge>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-8 text-center text-zinc-500 dark:text-zinc-400">
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No Inquiries</flux:heading>
            <flux:text class="mt-2">No contact inquiries found matching the current filter.</flux:text>
        </div>
    @endif
</div>
