<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnnouncementController extends Controller
{
    use AuthorizesRequests;

    protected $table = 'announcements';

    /**
     * Display the specified announcement.
     */
    public function show($id)
    {
        $announcement = Announcement::with(['author.roles'])->findOrFail($id);

        return view('livewire.announcements.show', compact('announcement'));
    }

    /**
     * Show the form for creating a new announcement.
     */
    public function create()
    {
        Gate::authorize('create', Announcement::class);

        $categories = Category::all();
        $tags = Tag::all();

        return view('announcements.create', compact('categories', 'tags'));
    }

    /**
     * Store a newly created announcement in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Announcement::class);
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|max:5000',
            'author_id' => 'nullable|exists:users,id',
            'tags' => 'array',
            'categories' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        // Create the announcement
        $announcement = Announcement::create([
            'title' => $request->title,
            'content' => $request->content,
            'author_id' => $request->author_id,
            'tags' => $request->tags,
            'categories' => $request->categories,
            'is_published' => $request->is_published,
            'published_at' => $request->is_published ? now() : null,
        ]);

        return view('livewire.announcements.show', ['announcement' => $announcement])
            ->with('status', 'Announcement created successfully!');
    }

    /**
     * Show the form for editing the specified announcement.
     */
    public function edit($id)
    {
        $announcement = Announcement::with(['author.roles'])->findOrFail($id);

        Gate::authorize('update', $announcement);

        return view('livewire.announcements.edit', compact('announcement'));
    }

    /**
     * Update the specified announcement in storage.
     */
    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);

        Gate::authorize('update', $announcement);

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required',
            'author_id' => 'nullable|exists:users,id',
            'tags' => 'array',
            'categories' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $announcement->update([
            'title' => $request->title,
            'content' => $request->content,
            'author_id' => $request->author_id,
            'tags' => $request->tags,
            'categories' => $request->categories,
            'is_published' => $request->is_published,
            'published_at' => $request->published_at,
        ]);

        return view('livewire.announcements.show', ['announcement' => $announcement])
            ->with('status', 'Announcement updated successfully!');
    }

    /**
     * Remove the specified announcement from storage.
     */
    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);

        Gate::authorize('delete', $announcement);

        $announcement->delete();

        return redirect()->route('acp.index', ['tab' => 'announcement-manager'])
            ->with('status', 'Announcement deleted successfully!');
    }
}
