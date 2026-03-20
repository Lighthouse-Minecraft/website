<?php

use App\Enums\MessageKind;
use App\Enums\ThreadType;
use App\Models\Message;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $moderationFilter = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function updatedModerationFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Message::with(['user', 'thread.topicable'])
            ->whereHas('thread', fn ($q) => $q->where('type', ThreadType::BlogComment))
            ->where('kind', MessageKind::Message);

        if ($this->moderationFilter === 'pending') {
            $query->where('is_pending_moderation', true);
        } elseif ($this->moderationFilter === 'approved') {
            $query->where('is_pending_moderation', false);
        }

        $query->orderBy($this->sortBy, $this->sortDirection);

        return [
            'comments' => $query->paginate(20),
        ];
    }
}; ?>

<div class="space-y-6 w-full">
    <flux:heading size="xl">Blog Comments</flux:heading>

    <div class="flex gap-3">
        <flux:select wire:model.live="moderationFilter" placeholder="All Comments" size="sm" class="w-48">
            <flux:select.option value="">All Comments</flux:select.option>
            <flux:select.option value="pending">Pending Moderation</flux:select.option>
            <flux:select.option value="approved">Approved</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$comments">
        <flux:table.columns>
            <flux:table.column>Author</flux:table.column>
            <flux:table.column>Comment</flux:table.column>
            <flux:table.column>Blog Post</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Posted</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($comments as $comment)
                <flux:table.row wire:key="blog-comment-{{ $comment->id }}">
                    <flux:table.cell>
                        <flux:link href="{{ route('profile.show', $comment->user) }}" wire:navigate>
                            {{ $comment->user->name }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-xs">
                        <span class="line-clamp-2 text-sm">{{ Str::limit($comment->body, 120) }}</span>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($comment->thread?->topicable)
                            <flux:link href="{{ route('blog.show', $comment->thread->topicable->slug) }}" wire:navigate>
                                {{ Str::limit($comment->thread->topicable->title, 40) }}
                            </flux:link>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($comment->is_pending_moderation)
                            <flux:badge color="amber" size="sm">Pending</flux:badge>
                        @else
                            <flux:badge color="green" size="sm">Approved</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $comment->created_at->format('M j, Y g:ia') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">No blog comments found.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
