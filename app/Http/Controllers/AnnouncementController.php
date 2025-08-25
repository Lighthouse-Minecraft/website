<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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
        $announcement = Announcement::with(['author.roles'])
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->firstOrFail();

        if (Auth::check() && Gate::denies('view', $announcement)) {
            abort(403);
        }

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
        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:announcements,title',
            'content' => 'required|max:5000',
            'author_id' => 'nullable|exists:users,id',
            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'categories' => ['array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'is_public' => 'boolean',
        ]);

        // Create the announcement
        $announcement = Announcement::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'content' => $validated['content'],
            'author_id' => $validated['author_id'] ?? null,
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? now() : null,
            'is_public' => $request->boolean('is_public', false),
        ]);

        // Sync categories and tags via pivot tables
        $announcement->categories()->sync($validated['categories'] ?? []);
        $announcement->tags()->sync($validated['tags'] ?? []);

        $announcement->load(['categories', 'tags']);

        return response()->view('livewire.announcements.show', [
            'announcement' => $announcement,
            'status' => 'Announcement created successfully!',
        ], 201);
    }

    /**
     * Show the form for editing the specified announcement.
     */
    public function edit($id)
    {
        $announcement = Announcement::with(['author.roles'])->findOrFail($id);

        Gate::authorize('update', $announcement);

        return response()->view('announcements.edit', [
            'announcement' => $announcement,
            'status' => 'Announcement edited successfully!',
        ], 200);
    }

    /**
     * Update the specified announcement in storage.
     */
    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);

        Gate::authorize('update', $announcement);

        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:announcements,title,'.$id,
            'content' => 'required',
            'author_id' => 'nullable|exists:users,id',
            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'categories' => ['array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'is_public' => 'boolean',
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'author_id' => $validated['author_id'] ?? null,
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => $validated['published_at'] ?? ($validated['is_published'] ?? false ? now() : null),
            'is_public' => $request->boolean('is_public', $announcement->is_public ?? false),
        ]);

        // Sync categories and tags via pivot tables
        $announcement->categories()->sync($validated['categories'] ?? []);
        $announcement->tags()->sync($validated['tags'] ?? []);

        $announcement->load(['categories', 'tags']);

        return response()->view('announcements.show', [
            'announcement' => $announcement,
            'status' => 'Announcement updated successfully!',
        ], 200);
    }

    /**
     * Show confirmation page before deleting the specified announcement (GET).
     */
    public function confirmDestroy($id)
    {
        $announcement = Announcement::with(['author.roles', 'tags', 'categories', 'comments'])->findOrFail($id);

        Gate::authorize('delete', $announcement);

        return view('livewire.announcements.destroy', [
            'announcement' => $announcement,
            'status' => null,
        ]);
    }

    /**
     * Remove the specified announcement from storage.
     */
    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);

        Gate::authorize('delete', $announcement);

        $announcement->delete();

        return response()->view('livewire.announcements.destroy', [
            'announcement' => $announcement,
            'tab' => 'announcement-manager',
            'status' => 'Announcement deleted successfully!',
        ], 200);
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
