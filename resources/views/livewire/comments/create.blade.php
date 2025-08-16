
<?php

namespace App\Livewire\Comments;

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    public string $commentContent = '';

    public int $commentable_id = 0;

    public string $commentable_type = '';

    public function getAnnouncementOptionsProperty(): array
    {
        return Announcement::all(['id', 'title'])
            ->map(fn ($a) => ['label' => $a->title, 'value' => (int) $a->id])
            ->toArray();
    }

    public function getBlogOptionsProperty(): array
    {
        return Blog::all(['id', 'title'])
            ->map(fn ($b) => ['label' => $b->title, 'value' => (int) $b->id])
            ->toArray();
    }

    public function render()
    {
        return view('livewire.comments.create', [
            'blogOptions' => $this->getBlogOptionsProperty(),
            'announcementOptions' => $this->getAnnouncementOptionsProperty(),
        ]);
    }

    public function saveComment()
    {
        if (! Auth::check()) {
            Flux::toast('You must be logged in to comment.', 'Error', variant: 'danger');

            return;
        }
        $this->validate([
            'commentContent' => 'required|string|max:2000',
            'commentable_id' => 'required|integer',
            'commentable_type' => 'required|string',
        ]);
        Comment::create([
            'content' => $this->commentContent,
            'author_id' => Auth::id(),
            'commentable_id' => $this->commentable_id,
            'commentable_type' => $this->commentable_type,
            'status' => 'pending',
        ]);
        Flux::toast('Comment created successfully!', 'Success', variant: 'success');

        return redirect()->route('acp.index', ['tab' => 'comment-manager']);
    }
}

?>

<div class="space-y-6">
    <flux:heading size="xl">Create New Comment</flux:heading>
    <form wire:submit.prevent="saveComment">
        <div class="space-y-6">
            <flux:editor label="Comment Content" wire:model="commentContent" />

            <select name="commentable_type" wire:model="commentable_type">
                <option value="">Select Type</option>
                <option value="App\\Models\\Blog">Blog</option>
                <option value="App\\Models\\Announcement">Announcement</option>
            </select>

            <select name="commentable_id" wire:model="commentable_id">
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
            </select>

            <div class="w-full text-right">
                    @if(auth()->check())
                        <flux:button wire:click="saveComment" icon="document-check" variant="primary">Save Comment</flux:button>
                    @else
                        <flux:button disabled icon="lock" variant="primary">Login to comment</flux:button>
                    @endif
            </div>
        </div>
    </form>
</div>
