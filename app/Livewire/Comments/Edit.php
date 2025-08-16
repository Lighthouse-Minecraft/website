<?php

namespace App\Livewire\Comments;

use App\Models\Comment;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Edit extends Component
{
    public Comment $comment;

    public string $commentContent = '';

    public int $commentable_id = 0;

    public string $commentable_type = '';

    public function mount(Comment $comment)
    {
        $this->comment = $comment;
        $this->commentContent = $comment->content;
        $this->commentable_id = $comment->commentable_id;
        $this->commentable_type = $comment->commentable_type;
    }

    public function saveComment()
    {
        if (! Auth::check()) {
            Flux::toast('You must be logged in to edit comments.', 'Error', variant: 'danger');

            return;
        }
        $this->validate([
            'commentContent' => 'required|string|max:2000',
        ]);
        $this->comment->update([
            'content' => $this->commentContent,
        ]);
        Flux::toast('Comment updated successfully!', 'Success', variant: 'success');

        return redirect()->route('acp.index', ['tab' => 'comment-manager']);
    }

    public function editComment()
    {
        if (! Auth::check()) {
            Flux::toast('You must be logged in to edit comments.', 'Error', variant: 'danger');

            return;
        }
        $this->validate([
            'commentContent' => 'required|string|max:2000',
        ]);
        $this->comment->update([
            'content' => $this->commentContent,
        ]);
        Flux::toast('Comment updated successfully!', 'Success', variant: 'success');

        return redirect()->route('acp.index', ['tab' => 'comment-manager']);
    }

    public function deleteComment()
    {
        if (! Auth::check()) {
            Flux::toast('You must be logged in to delete comments.', 'Error', variant: 'danger');

            return;
        }
        $this->comment->delete();
        Flux::toast('Comment deleted successfully!', 'Success', variant: 'success');

        return redirect()->route('acp.index', ['tab' => 'comment-manager']);
    }
}
