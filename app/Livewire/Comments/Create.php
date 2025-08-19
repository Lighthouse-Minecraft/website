<?php

namespace App\Livewire\Comments;

use App\Enums\StaffRank;
use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use Flux\Flux;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector as LivewireRedirector;

class Create extends Component
{
    public string $commentContent = '';

    public ?int $commentable_id = null;

    public string $commentable_type = '';

    public function getAnnouncementOptionsProperty(): array
    {
        $query = Announcement::query()->where('is_published', true);
        if (Schema::hasColumn('announcements', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->get(['id', 'title'])
            ->map(fn ($a) => ['label' => $a->title, 'value' => (int) $a->id])
            ->toArray();
    }

    public function getBlogOptionsProperty(): array
    {
        $query = Blog::query()->where('is_published', true);
        if (Schema::hasColumn('blogs', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->get(['id', 'title'])
            ->map(fn ($b) => ['label' => $b->title, 'value' => (int) $b->id])
            ->toArray();
    }

    public function getResourceOptionsProperty(): array
    {
        if ($this->commentable_type === 'announcement') {
            return $this->getAnnouncementOptionsProperty();
        }
        if ($this->commentable_type === 'blog') {
            return $this->getBlogOptionsProperty();
        }

        return [];
    }

    public function render()
    {
        return view('livewire.comments.create', [
            'resourceOptions' => $this->getResourceOptionsProperty(),
            'blogOptions' => $this->getBlogOptionsProperty(),
            'announcementOptions' => $this->getAnnouncementOptionsProperty(),
        ]);
    }

    public function saveComment(): RedirectResponse|LivewireRedirector
    {
        if (! Auth::check()) {
            Flux::toast('You must be logged in to comment.', 'Error', variant: 'danger');

            return redirect()->back();
        }
        $rules = [
            'commentContent' => 'required|string|max:2000',
            'commentable_type' => ['required', 'string', 'in:announcement,blog'],
            'commentable_id' => [Rule::requiredIf((bool) $this->commentable_type), 'nullable', 'integer', 'gt:0'],
        ];

        if ($this->commentable_type === 'announcement') {
            $rules['commentable_id'][] = Rule::exists('announcements', 'id');
        } elseif ($this->commentable_type === 'blog') {
            $rules['commentable_id'][] = Rule::exists('blogs', 'id');
        }

        $messages = [
            'commentContent.required' => 'Please enter your comment.',
            'commentContent.max' => 'Comments cannot exceed 2000 characters.',
            'commentable_type.required' => 'Select what you are commenting on.',
            'commentable_type.in' => 'Select a valid type (Announcement or Blog).',
            'commentable_id.required' => 'Please choose a specific item.',
            'commentable_id.gt' => 'Please choose a specific item.',
            'commentable_id.exists' => 'The selected item could not be found.',
        ];

        $this->validate($rules, $messages);
        $snapshotTitle = null;
        $snapshotContent = null;
        if ($this->commentable_type === 'announcement') {
            $ann = Announcement::withTrashed()->find($this->commentable_id);
            if ($ann) {
                $snapshotTitle = $ann->title;
                $snapshotContent = $ann->content;
            }
        } elseif ($this->commentable_type === 'blog') {
            $blog = Blog::withTrashed()->find($this->commentable_id);
            if ($blog) {
                $snapshotTitle = $blog->title;
                $snapshotContent = $blog->content;
            }
        }

        $user = Auth::user();
        $data = [
            'content' => $this->commentContent,
            'author_id' => Auth::id(),
            'commentable_id' => $this->commentable_id,
            'commentable_type' => $this->commentable_type,
            'commentable_title' => $snapshotTitle,
            'commentable_content' => $snapshotContent,
        ];

        if ($user && ($user->isAdmin() || ($user->staff_rank ?? null) === StaffRank::Officer)) {
            $data['status'] = 'approved';
            $data['needs_review'] = false;
        } else {
            $data['status'] = 'needs_review';
            $data['needs_review'] = true;
        }

        $comment = Comment::create($data);
        Flux::toast('Comment created successfully!', 'Success', variant: 'success');

        return $this->redirectAfterMutation($comment->commentable_type, (int) $comment->commentable_id, $comment->id);
    }

    /**
     * Decide where to redirect a user after creating a comment.
     */
    protected function redirectAfterMutation(string $type, int $id, int $commentId): RedirectResponse|LivewireRedirector
    {
        $from = (string) request()->query('from', '');
        $return = (string) request()->query('return', '');

        if ($from === 'acp' || request()->routeIs('acp.*')) {
            return redirect()->route('acp.index', ['tab' => 'comment-manager']);
        }

        if ($return !== '') {
            return redirect()->to($return);
        }

        $type = strtolower($type);

        if ($type === 'blog' && $id > 0) {
            return redirect()->route('blogs.show', $id);
        }
        if ($type === 'announcement' && $id > 0) {
            return redirect()->route('announcements.show', $id);
        }

        return redirect()->route('comments.show', $commentId);
    }

    public function updatedCommentableType()
    {
        $this->commentable_id = null;
    }
}
