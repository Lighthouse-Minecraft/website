<?php

namespace App\Livewire\Comments;

use App\Enums\StaffRank;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CommentsSection extends Component
{
    public Model $parent;

    public string $content = '';

    protected $rules = [
        'content' => 'required|string|max:2000',
    ];

    public function mount($parent)
    {
        $this->parent = $parent;
    }

    public function addComment()
    {
        if (! Auth::check()) {
            session()->flash('error', 'You must be logged in to comment.');

            return;
        }

        $this->validate();
        $comment = new Comment;
        $comment->content = $this->content;
        $comment->author_id = Auth::id();
        $author = Auth::user();
        if ($author && ($author->isAdmin() || $author->staff_rank === StaffRank::Officer)) {
            $comment->status = 'approved';
            $comment->needs_review = false;
        } else {
            $comment->status = 'needs_review';
            $comment->needs_review = true;
        }
        $this->parent->comments()->save($comment);
        $this->reset('content');
    }

    public function markReviewed($commentId)
    {
        $user = Auth::user();
        if (! ($user && ($user->isAdmin() || $user->staff_rank === StaffRank::Officer))) {
            return;
        }
        $comment = $this->parent->comments()->where('id', $commentId)->first();
        if ($comment && $comment->needs_review && ! $comment->reviewed_by) {
            $comment->reviewed_by = $user->id;
            $comment->reviewed_at = now();
            $comment->needs_review = false;
            $comment->save();
        }
    }

    public function render()
    {
        $user = Auth::user();

        $query = $this->parent
            ->comments()
            ->with(['author'])
            ->orderBy('created_at', 'asc');

        if (! $user) {
            // Guests: only approved/reviewed comments
            $query->where('needs_review', false);
        } elseif ($user->isAdmin() || $user->staff_rank === StaffRank::Officer) {
            // Admins/Officers: can see all (including pending)
        } else {
            // Authenticated regular users: see approved OR their own pending comments
            $query->where(function ($q) use ($user) {
                $q->where('needs_review', false)
                    ->orWhere('author_id', $user->id);
            });
        }

        $comments = $query->get();

        return view('livewire.comments.comments-section', [
            'comments' => $comments,
        ]);
    }
}
