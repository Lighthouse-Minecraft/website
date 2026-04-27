<?php

use App\Enums\ThreadType;
use App\Models\DisciplineReport;
use App\Models\Thread;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public DisciplineReport $report;

    public string $topicSubject = '';

    public function mount(DisciplineReport $report): void
    {
        $this->authorize('view', $report);
        $this->report = $report->load(['subject', 'reporter', 'publisher', 'category', 'violatedRules', 'images']);
    }

    #[Computed]
    public function isStaff(): bool
    {
        return auth()->user()->hasRole('Staff Access');
    }

    #[Computed]
    public function topics()
    {
        return $this->report->topics()
            ->withCount('messages')
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    public function startTopic(): void
    {
        $this->authorize('createTopic', [Thread::class, $this->report]);

        $this->validate([
            'topicSubject' => 'required|string|min:3|max:255',
        ]);

        $thread = \App\Actions\CreateTopic::run(
            $this->report,
            auth()->user(),
            $this->topicSubject,
        );

        $this->topicSubject = '';

        Flux::modal('create-topic-modal')->close();

        $this->redirect(route('discussions.show', $thread), navigate: true);
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div>
        <flux:heading size="xl">Staff Report</flux:heading>
        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ ($report->published_at ?? $report->created_at)->format('M j, Y') }}</span>
        </div>
    </div>

    {{-- Subject --}}
    <flux:card>
        <div class="flex items-center gap-3 mb-4">
            <flux:avatar size="sm" :src="$report->subject->avatarUrl()" :initials="$report->subject->initials()" />
            <div>
                <flux:text class="font-bold text-sm">Subject</flux:text>
                <a href="{{ route('profile.show', $report->subject) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $report->subject->name }}</a>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($report->category)
                <flux:badge color="{{ $report->category->color }}">{{ $report->category->name }}</flux:badge>
            @endif
            <flux:badge color="{{ $report->location->color() }}">{{ $report->location->label() }}</flux:badge>
            <flux:badge color="{{ $report->severity->color() }}">{{ $report->severity->label() }}</flux:badge>
            <flux:badge color="{{ $report->status->color() }}">{{ $report->status->label() }}</flux:badge>
        </div>
    </flux:card>

    {{-- Report Details --}}
    <flux:card>
        <div class="space-y-4">
            <div>
                <flux:text class="font-bold text-sm">What Happened</flux:text>
                <div class="mt-1 prose prose-sm dark:prose-invert max-w-none">
                    {!! Str::markdown($report->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>
            </div>

            @if($report->witnesses)
                <div>
                    <flux:text class="font-bold text-sm">Witnesses</flux:text>
                    <flux:text class="mt-1">{{ $report->witnesses }}</flux:text>
                </div>
            @endif

            <div>
                <flux:text class="font-bold text-sm">Actions Taken</flux:text>
                <div class="mt-1 prose prose-sm dark:prose-invert max-w-none">
                    {!! Str::markdown($report->actions_taken, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>
            </div>

            @if($report->violatedRules->isNotEmpty())
                <div>
                    <flux:text class="font-bold text-sm">Rules Violated</flux:text>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach($report->violatedRules as $rule)
                            <flux:badge color="red" size="sm">{{ $rule->title }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </flux:card>

    {{-- Staff Info --}}
    @if($this->isStaff)
        <flux:card>
            <flux:heading size="sm" class="mb-3">Staff Details</flux:heading>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <flux:text class="font-bold text-sm">Reporter</flux:text>
                    <div class="flex items-center gap-2 mt-1">
                        <flux:avatar size="xs" :src="$report->reporter->avatarUrl()" :initials="$report->reporter->initials()" />
                        <a href="{{ route('profile.show', $report->reporter) }}" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">{{ $report->reporter->name }}</a>
                    </div>
                </div>
                @if($report->publisher)
                    <div>
                        <flux:text class="font-bold text-sm">Published By</flux:text>
                        <div class="flex items-center gap-2 mt-1">
                            <flux:avatar size="xs" :src="$report->publisher->avatarUrl()" :initials="$report->publisher->initials()" />
                            <a href="{{ route('profile.show', $report->publisher) }}" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">{{ $report->publisher->name }}</a>
                        </div>
                    </div>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <flux:text class="font-bold text-sm">Created</flux:text>
                    <flux:text>{{ $report->created_at->format('M j, Y g:i A') }}</flux:text>
                </div>
                @if($report->published_at)
                    <div>
                        <flux:text class="font-bold text-sm">Published</flux:text>
                        <flux:text>{{ $report->published_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                @endif
            </div>
        </flux:card>
    @else
        <flux:card>
            <div>
                <flux:text class="font-bold text-sm">Date</flux:text>
                <flux:text>{{ ($report->published_at ?? $report->created_at)->format('M j, Y g:i A') }}</flux:text>
            </div>
        </flux:card>
    @endif

    {{-- Evidence --}}
    @if($report->images->isNotEmpty())
        <flux:card>
            <flux:heading size="sm" class="mb-3">Evidence</flux:heading>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                @foreach($report->images as $image)
                    <a href="{{ $image->url() }}" target="_blank" rel="noopener" wire:key="evidence-img-{{ $image->id }}">
                        <img src="{{ $image->url() }}" alt="{{ $image->original_filename }}" class="rounded-lg object-cover aspect-square w-full hover:opacity-90 transition" />
                    </a>
                @endforeach
            </div>
        </flux:card>
    @endif

    {{-- Discussion Topics --}}
    @if($report->isPublished())
        <flux:card>
            <div class="flex items-center justify-between mb-3">
                <flux:heading size="sm">Discussions</flux:heading>
                @can('createTopic', [\App\Models\Thread::class, $report])
                    <flux:button size="xs" variant="primary" x-on:click="$flux.modal('create-topic-modal').show()">
                        Start Discussion
                    </flux:button>
                @endcan
            </div>

            @if($this->topics->isEmpty())
                <flux:text variant="subtle" class="text-sm">No discussions yet.</flux:text>
            @else
                <div class="space-y-2">
                    @foreach($this->topics as $topic)
                        <a href="{{ route('discussions.show', $topic) }}" wire:navigate wire:key="topic-{{ $topic->id }}" class="flex items-center justify-between rounded border border-zinc-200 dark:border-zinc-700 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                            <div>
                                <flux:text class="font-medium text-sm">{{ $topic->subject }}</flux:text>
                                <flux:text variant="subtle" class="text-xs">{{ $topic->created_at->diffForHumans() }}</flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($topic->is_locked)
                                    <flux:badge color="red" size="sm">Locked</flux:badge>
                                @endif
                                <flux:badge color="zinc" size="sm">{{ $topic->messages_count }} {{ Str::plural('message', $topic->messages_count) }}</flux:badge>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </flux:card>
    @endif

    {{-- Create Topic Modal --}}
    <flux:modal name="create-topic-modal" class="w-full md:w-1/3">
        <div class="space-y-4">
            <flux:heading size="lg">Start Discussion</flux:heading>
            <flux:text variant="subtle">Start a discussion about this report. Relevant participants will be added automatically.</flux:text>

            <form wire:submit="startTopic">
                <flux:field>
                    <flux:label>Subject</flux:label>
                    <flux:input wire:model="topicSubject" placeholder="Discussion subject..." />
                    <flux:error name="topicSubject" />
                </flux:field>

                <div class="mt-4 flex justify-end">
                    <flux:button type="submit" variant="primary">Start Discussion</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
