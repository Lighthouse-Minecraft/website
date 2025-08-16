<?php

namespace App\Http\Controllers;

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
        $announcementComments = Comment::where('commentable_type', 'App\\Models\\Announcement')
            ->latest()->paginate(10, ['*'], 'announcement_page');
        $blogComments = Comment::where('commentable_type', 'App\\Models\\Blog')
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
            'commentable_id' => 'required|integer',
            'commentable_type' => 'required|string',
        ]);
        $validated['author_id'] = Auth::id();
        Comment::create($validated);

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
     * Remove the specified comment from storage.
     */
    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);
        Gate::authorize('delete', $comment);
        $comment->delete();

        return redirect()->back()->with('status', 'Comment deleted successfully!');
    }

    /**
     * Approve the specified comment.
     */
    public function approve(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        Gate::authorize('update', $comment);
        $comment->status = 'approved';
        $comment->save();

        return redirect()->back()->with('status', 'Comment approved successfully!');
    }

    /**
     * Reject the specified comment.
     */
    public function reject(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        Gate::authorize('update', $comment);
        $comment->status = 'rejected';
        $comment->save();

        return redirect()->back()->with('status', 'Comment rejected successfully!');
    }
}
