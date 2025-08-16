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
        ]);
        Flux::toast('Comment created successfully!', 'Success', variant: 'success');

        return redirect()->route('acp.index', ['tab' => 'comment-manager']);
    }
}
