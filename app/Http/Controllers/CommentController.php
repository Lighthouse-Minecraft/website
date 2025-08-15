<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * Display a listing of comments.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Comment::class);
        // TODO: Add pagination, filtering, etc.
        $comments = Comment::latest()->paginate(20);

        return view('comments.index', compact('comments'));
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
            'author_id' => 'nullable|exists:users,id',
            'commentable_id' => 'required|integer',
            'commentable_type' => 'required|string',
        ]);
        $comment = Comment::create($validated);

        return response()->view('comments.show', [
            'comment' => $comment,
            'status' => 'Comment created successfully!',
        ], 201);
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
}
