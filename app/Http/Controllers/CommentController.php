<?php

namespace App\Http\Controllers;

use App\Enums\StaffRank;
use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * Display a listing of comments.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Comment::class);
        $announcementComments = Comment::where('commentable_type', 'announcement')
            ->latest()->paginate(10, ['*'], 'announcement_page');
        $blogComments = Comment::where('commentable_type', 'blog')
            ->latest()->paginate(10, ['*'], 'blog_page');

        return view('comments.index', [
            'announcementComments' => $announcementComments,
            'blogComments' => $blogComments,
        ]);
    }

    /**
     * Display the specified comment.
     */
    public function show($id)
    {
        $comment = Comment::findOrFail($id);
        Gate::authorize('view', $comment);

        return view('comments.show', compact('comment'));
    }

    /**
     * Store a newly created comment in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Comment::class);
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'commentable_id' => 'required|integer|gt:0',
            'commentable_type' => 'required|string|in:announcement,blog',
        ]);

        $type = strtolower($validated['commentable_type']);
        $id = (int) $validated['commentable_id'];

        // Snapshot the parent title/content for display even if parent is later deleted
        $snapshotTitle = null;
        $snapshotContent = null;
        if ($type === 'announcement') {
            $parent = Announcement::withTrashed()->find($id);
            if ($parent) {
                $snapshotTitle = $parent->title;
                $snapshotContent = $parent->content;
            }
        } elseif ($type === 'blog') {
            $parent = Blog::withTrashed()->find($id);
            if ($parent) {
                $snapshotTitle = $parent->title;
                $snapshotContent = $parent->content;
            }
        }

        $user = Auth::user();

        $data = [
            'content' => $validated['content'],
            'author_id' => Auth::id(),
            'commentable_id' => $id,
            'commentable_type' => $type,
            'commentable_title' => $snapshotTitle,
            'commentable_content' => $snapshotContent,
        ];

        if ($user && ($user->isAdmin() || $user->staff_rank === StaffRank::Officer)) {
            $data['status'] = 'approved';
            $data['needs_review'] = false;
        } else {
            $data['status'] = 'needs_review';
            $data['needs_review'] = true;
        }

        Comment::create($data);

        return redirect()->back()->with('success', 'Comment posted successfully!');
    }

    /**
     * Show the form for creating a new comment.
     */
    public function create(Request $request)
    {
        Gate::authorize('create', Comment::class);

        $blogs = Blog::all();
        $announcements = Announcement::all();

        return view('comments.create', compact('blogs', 'announcements'));
    }

    /**
     * Show the form for editing the specified comment.
     */
    public function edit($id)
    {
        $comment = Comment::findOrFail($id);
        Gate::authorize('update', $comment);

        return view('comments.edit', [
            'commentable_type' => $comment->commentable_type,
            'commentable_id' => $comment->commentable_id,
            'commentContent' => $comment->content,
            'comment' => $comment,
        ]);
    }

    /**
     * Update the specified comment in storage.
     */
    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        Gate::authorize('update', $comment);
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);
        $comment->update($validated);

        return redirect()->back()->with('status', 'Comment updated successfully!');
    }

    /**
     * Show confirmation page before deleting the specified comment (GET).
     */
    public function confirmDestroy($id)
    {
        $comment = Comment::with(['author'])->findOrFail($id);

        Gate::authorize('delete', $comment);

        // Resolve the parent manually to avoid morphTo instantiation issues in the view
        [$parent, $parentType] = $this->resolveParent($comment);

        return view('livewire.comments.destroy', [
            'comment' => $comment,
            'parent' => $parent,
            'parentType' => $parentType,
            'status' => null,
        ]);
    }

    /**
     * Remove the specified comment from storage.
     */
    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);
        Gate::authorize('delete', $comment);

        // Resolve the parent before deletion for display after delete
        [$parent, $parentType] = $this->resolveParent($comment);

        $comment->delete();

        return response()->view('livewire.comments.destroy', [
            'comment' => $comment,
            'parent' => $parent,
            'parentType' => $parentType,
            'tab' => 'comment-manager',
            'status' => 'Comment deleted successfully!',
        ], 200);
    }

    /**
     * Resolve the parent model for a comment based on its stored type/id.
     * Returns [parentModel|null, parentType|null]
     */
    protected function resolveParent(Comment $comment): array
    {
        $rawType = (string) ($comment->getRawOriginal('commentable_type') ?? '');
        $normalized = match ($rawType) {
            'App\\Models\\Blog', Blog::class, 'Blog', 'blog' => 'blog',
            'App\\Models\\Announcement', Announcement::class, 'Announcement', 'announcement' => 'announcement',
            default => strtolower($rawType),
        };

        if ($normalized === 'blog') {
            return [Blog::withTrashed()->find($comment->commentable_id), 'blog'];
        }
        if ($normalized === 'announcement') {
            return [Announcement::withTrashed()->find($comment->commentable_id), 'announcement'];
        }

        return [null, null];
    }

    /**
     * Approve the specified comment.
     */
    public function approve(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        Gate::authorize('review', $comment);
        $comment->status = 'approved';
        $comment->reviewed_by = Auth::id();
        $comment->reviewed_at = now();
        $comment->needs_review = false;
        $comment->save();

        return redirect()->back()->with('status', 'Comment approved successfully!');
    }

    /**
     * Reject the specified comment.
     */
    public function reject(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        Gate::authorize('review', $comment);
        $comment->status = 'rejected';
        $comment->reviewed_by = Auth::id();
        $comment->reviewed_at = now();
        $comment->needs_review = false;
        $comment->save();

        return redirect()->back()->with('status', 'Comment rejected successfully!');
    }
}
