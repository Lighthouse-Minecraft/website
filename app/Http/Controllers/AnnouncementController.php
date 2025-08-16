<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnnouncementController extends Controller
{
    use AuthorizesRequests;

    protected $table = 'announcements';

    public function index(Request $request): View
    {
        $query = Announcement::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                    ->orWhere('content', 'like', "%$search%");
            });
        }

        if ($request->filled('category')) {
            $categoryId = $request->input('category');
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        if ($request->filled('tag')) {
            $tagId = $request->input('tag');
            $query->whereHas('tags', function ($q) use ($tagId) {
                $q->where('tags.id', $tagId);
            });
        }

        $announcements = $query->latest('id')->paginate(10);

        return view('announcements.index', compact('announcements'));
    }

    /**
     * Display the specified announcement.
     */
    public function show($id)
    {
        Gate::authorize('view', Announcement::class);

        $announcement = Announcement::with(['author.roles'])->findOrFail($id);

        return view('announcements.show', compact('announcement'));
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

        return view('announcements.edit', compact('announcement'));
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

    // -------------------- Category Management --------------------
    public function addCategory(Request $request, $announcement)
    {
        Gate::authorize('create', Announcement::class);
        $announcement = Announcement::findOrFail($announcement);
        $request->validate(['category' => 'required|string|max:255']);
        $category = Category::firstOrCreate(['name' => $request->category]);
        $announcement->categories()->attach($category->id);

        return back()->with('status', 'Category created and attached.');
    }

    public function attachCategory(Request $request, $announcement)
    {
        Gate::authorize('update', Announcement::class);
        $announcement = Announcement::findOrFail($announcement);
        $request->validate(['category_id' => 'required|exists:categories,id']);
        $announcement->categories()->attach($request->category_id);

        return back()->with('status', 'Category attached.');
    }

    /**
     * Remove a category from announcement (admin only).
     */
    public function removeCategory(Request $request, $announcement)
    {
        Gate::authorize('update', Announcement::class);
        $announcement = Announcement::findOrFail($announcement);
        $request->validate(['category_id' => 'required|exists:categories,id']);
        $announcement->categories()->detach($request->category_id);

        return back()->with('status', 'Category removed.');
    }

    // -------------------- Tag Management --------------------
    public function addTag(Request $request, $announcement)
    {
        Gate::authorize('create', Announcement::class);
        $announcement = Announcement::findOrFail($announcement);
        $request->validate(['tag' => 'required|string|max:255']);
        $tag = Tag::firstOrCreate(['name' => $request->tag]);
        $announcement->tags()->attach($tag->id);

        return back()->with('status', 'Tag created and attached.');
    }

    public function attachTag(Request $request, $announcement)
    {
        Gate::authorize('update', Announcement::class);
        $announcement = Announcement::findOrFail($announcement);
        $request->validate(['tag_id' => 'required|exists:tags,id']);
        $announcement->tags()->attach($request->tag_id);

        return back()->with('status', 'Tag attached.');
    }

    /**
     * Remove a tag from announcement (admin only).
     */
    public function removeTag(Request $request, $announcement)
    {
        Gate::authorize('update', Announcement::class);
        $announcement = Announcement::findOrFail($announcement);
        $request->validate(['tag_id' => 'required|exists:tags,id']);
        $announcement->tags()->detach($request->tag_id);

        return back()->with('status', 'Tag removed.');
    }
}
