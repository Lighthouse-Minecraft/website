<?php

namespace App\Livewire\Comments;

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use Flux\Flux;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector as LivewireRedirector;

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

    public function saveComment(): RedirectResponse|LivewireRedirector
    {
        if (! Auth::check()) {
            Flux::toast('You must be logged in to edit comments.', 'Error', variant: 'danger');

            return redirect()->back();
        }
        $this->validate([
            'commentContent' => 'required|string|max:2000',
        ]);
        $this->comment->update([
            'content' => $this->commentContent,
        ]);
        Flux::toast('Comment updated successfully!', 'Success', variant: 'success');

        return $this->redirectAfterMutation();
    }

    public function editComment(): RedirectResponse|LivewireRedirector
    {
        if (! Auth::check()) {
            Flux::toast('You must be logged in to edit comments.', 'Error', variant: 'danger');

            return redirect()->back();
        }
        $this->validate([
            'commentContent' => 'required|string|max:2000',
        ]);
        $this->comment->update([
            'content' => $this->commentContent,
        ]);
        Flux::toast('Comment updated successfully!', 'Success', variant: 'success');

        return $this->redirectAfterMutation();
    }

    public function deleteComment(): RedirectResponse|LivewireRedirector
    {
        if (! Auth::check()) {
            Flux::toast('You must be logged in to delete comments.', 'Error', variant: 'danger');

            return redirect()->back();
        }
        $this->comment->delete();
        Flux::toast('Comment deleted successfully!', 'Success', variant: 'success');

        return $this->redirectAfterMutation();
    }

    /**
     * Decide where to redirect a user after editing/deleting a comment.
     */
    protected function redirectAfterMutation(): RedirectResponse|LivewireRedirector
    {
        $from = (string) request()->query('from', '');
        $return = (string) request()->query('return', '');

        if ((($from === 'acp') || request()->routeIs('acp.*')) && Gate::allows('viewAny', Comment::class)) {
            return redirect()->route('acp.index', ['tab' => 'comment-manager']);
        }

        if ($return !== '') {
            return redirect()->to($return);
        }

        $type = strtolower((string) $this->comment->commentable_type);
        $id = (int) $this->comment->commentable_id;

        if ($type === 'blog' && $id > 0) {
            return redirect()->route('blogs.show', $id);
        }
        if ($type === 'announcement' && $id > 0) {
            return redirect()->route('announcements.show', $id);
        }

        return redirect()->route('comments.show', $this->comment->id);
    }

    /**
     * Computed: Blog select options for the edit view.
     *
     * @return array<int, array{label:string,value:int}>
     */
    public function getBlogOptionsProperty(): array
    {
        return Blog::query()
            ->get(['id', 'title'])
            ->map(fn ($b) => ['label' => (string) $b->title, 'value' => (int) $b->id])
            ->toArray();
    }

    /**
     * Computed: Announcement select options for the edit view.
     *
     * @return array<int, array{label:string,value:int}>
     */
    public function getAnnouncementOptionsProperty(): array
    {
        return Announcement::query()
            ->get(['id', 'title'])
            ->map(fn ($a) => ['label' => (string) $a->title, 'value' => (int) $a->id])
            ->toArray();
    }
}
