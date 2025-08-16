<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public int $commentable_id = 0;
    public string $commentContent = '';
    public string $commentable_type = '';

    public function getBlogOptionsProperty(): array
    {
        return Blog::query()
            ->get(['id', 'title'])
            ->map(fn ($b) => ['label' => $b->title, 'value' => (int) $b->id])
            ->toArray();
    }

    public function getAnnouncementOptionsProperty(): array
    {
        return Announcement::query()
            ->get(['id', 'title'])
            ->map(fn ($a) => ['label' => $a->title, 'value' => (int) $a->id])
            ->toArray();
    }

    public function saveComment()
    {
        $this->validate([
            'commentContent' => 'required|string|max:2000',
            'commentable_id' => 'required|integer',
            'commentable_type' => 'required|string',
        ]);

        Comment::create([
            'content' => $this->commentContent,
            'author_id' => auth()->id(),
            'commentable_id' => $this->commentable_id,
            'commentable_type' => $this->commentable_type,
        ]);

        Flux::toast('Comment updated successfully!', 'Success', variant: 'success');
        return redirect()->route('acp.index', ['tab' => 'comment-manager']);
    }
};

?>

<flux:card class="max-w-2xl mx-auto mt-10">
    <div class="space-y-6 p-6">
        <flux:heading size="xl" class="mb-4">Edit Comment</flux:heading>
        @if(auth()->id() === $comment->author_id)
            <form wire:submit.prevent="saveComment">
                <div class="space-y-6">
                    <flux:editor label="Comment Content" wire:model="commentContent" />
                    <flux:dropdown label="Type" wire:model="commentable_type">
                        <option value="">Select Type</option>
                        <option value="App\\Models\\Blog">Blog</option>
                        <option value="App\\Models\\Announcement">Announcement</option>
                    </flux:dropdown>
                    <flux:dropdown label="Resource" wire:model="commentable_id">
                        <option value="">Select Resource</option>
                        @if($commentable_type === 'App\\Models\\Blog')
                            @foreach ($blogOptions as $blog)
                                <option value="{{ $blog['value'] }}">{{ $blog['label'] }}</option>
                            @endforeach
                        @elseif($commentable_type === 'App\\Models\\Announcement')
                            @foreach ($announcementOptions as $announcement)
                                <option value="{{ $announcement['value'] }}">{{ $announcement['label'] }}</option>
                            @endforeach
                        @endif
                    </flux:dropdown>
                    <div class="w-full text-right flex gap-2">
                        <flux:button wire:navigate href="{{ route('acp.index', ['tab' => 'comment-manager']) }}" variant="primary" class="mx-4">Cancel</flux:button>
                        <flux:button wire:click="saveComment" icon="document-check" variant="primary">Update Comment</flux:button>
                    </div>
                </div>
            </form>
        @else
            <div class="text-red-500 font-semibold">You are not authorized to edit this comment.</div>
        @endif
    </div>
</flux:card>
