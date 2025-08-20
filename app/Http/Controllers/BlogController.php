<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    use AuthorizesRequests;

    protected $table = 'blogs';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = Blog::query();

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

        $blogs = $query->latest('id')->paginate(10);

        return view('blogs.index', compact('blogs'));
    }

    /**
     * Display the specified blog.
     */
    public function show($id)
    {
        $query = Blog::with(['author.roles']);

        if (is_string($id) && ctype_digit($id)) {
            $blog = $query->findOrFail((int) $id);
        } else {
            $blog = $query->where('slug', $id)->firstOrFail();
        }

        if (Auth::check() && Gate::denies('view', $blog)) {
            abort(403);
        }

        return view('blogs.show', compact('blog'));
    }

    /**
     * Show the form for creating a new blog.
     */
    public function create()
    {
        Gate::authorize('create', Blog::class);

        $categories = Category::all();
        $tags = Tag::all();

        return view('blogs.create', compact('categories', 'tags'));
    }

    /**
     * Store a newly created blog in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Blog::class);
        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:blogs,title',
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

        // Generate slug from title
        $slug = Str::slug($validated['title']);

        // Create the blog
        $blog = Blog::create([
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'],
            'author_id' => $validated['author_id'] ?? null,
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? now() : null,
            'is_public' => $request->boolean('is_public', false),
        ]);

        // Attach tags and categories if provided
        $blog->tags()->sync($validated['tags'] ?? []);
        $blog->categories()->sync($validated['categories'] ?? []);

        $blog->load(['categories', 'tags']);

        return response()->view('livewire.blogs.show', [
            'blog' => $blog,
            'status' => 'Blog created successfully!',
        ], 201);
    }

    /**
     * Show the form for editing the specified blog.
     */
    public function edit($id)
    {
        $blog = Blog::with(['author.roles'])->findOrFail($id);

        Gate::authorize('update', $blog);

        return response()->view('blogs.edit', [
            'blog' => $blog,
            'status' => 'Blog edited successfully!',
        ], 200);
    }

    /**
     * Update the specified blog in storage.
     */
    public function update(Request $request, $id)
    {
        $blog = Blog::findOrFail($id);

        Gate::authorize('update', $blog);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
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

        $blog->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'author_id' => $validated['author_id'] ?? null,
            'is_published' => $validated['is_published'] ?? $blog->is_published,
            'published_at' => $validated['published_at'] ?? ($validated['is_published'] ?? $blog->is_published ? ($blog->published_at ?? now()) : null),
            'is_public' => $request->boolean('is_public', $blog->is_public),
        ]);

        // Sync categories and tags via pivot tables
        $blog->tags()->sync($validated['tags'] ?? []);
        $blog->categories()->sync($validated['categories'] ?? []);

        $blog->load(['categories', 'tags']);

        return response()->view('blogs.show', [
            'blog' => $blog,
            'status' => 'Blog updated successfully!',
        ], 200);
    }

    /**
     * Show confirmation page before deleting the specified blog (GET).
     */
    public function confirmDestroy($id)
    {
        $blog = Blog::with(['author.roles', 'tags', 'categories', 'comments'])->findOrFail($id);

        Gate::authorize('delete', $blog);

        return view('livewire.blogs.destroy', [
            'blog' => $blog,
            'status' => null,
        ]);
    }

    /**
     * Remove the specified blog from storage.
     */
    public function destroy($id)
    {
        $blog = Blog::findOrFail($id);

        Gate::authorize('delete', $blog);

        $blog->delete();

        return response()->view('livewire.blogs.destroy', [
            'blog' => $blog,
            'tab' => 'blog-manager',
            'status' => 'Blog deleted successfully!',
        ], 200);
    }

    // -------------------- Category Management --------------------
    public function addCategory(Request $request, $blog)
    {
        Gate::authorize('create', Blog::class);
        $blog = Blog::findOrFail($blog);
        $request->validate(['category' => 'required|string|max:255']);
        $category = Category::firstOrCreate(['name' => $request->category]);
        $blog->categories()->attach($category->id);

        return back()->with('status', 'Category created and attached.');
    }

    public function attachCategory(Request $request, $blog)
    {
        Gate::authorize('update', Blog::class);
        $blog = Blog::findOrFail($blog);
        $request->validate(['category_id' => 'required|exists:categories,id']);
        $blog->categories()->attach($request->category_id);

        return back()->with('status', 'Category attached.');
    }

    /**
     * Remove a category from blog (admin only).
     */
    public function removeCategory(Request $request, $blog)
    {
        Gate::authorize('update', Blog::class);
        $blog = Blog::findOrFail($blog);
        $request->validate(['category_id' => 'required|exists:categories,id']);
        $blog->categories()->detach($request->category_id);

        return back()->with('status', 'Category removed.');
    }

    // -------------------- Tag Management --------------------
    /**
     * Add a new tag to the blog (admin only).
     */
    public function addTag(Request $request, $blog)
    {
        Gate::authorize('create', Blog::class);
        $blog = Blog::findOrFail($blog);
        $request->validate(['tag' => 'required|string|max:255']);
        $tag = Tag::firstOrCreate(['name' => $request->tag]);
        $blog->tags()->attach($tag->id);

        return back()->with('status', 'Tag created and attached.');
    }

    /**
     * Create a new tag via TaxonomyController and attach to blog (admin only).
     */
    public function attachTag(Request $request, $blog)
    {
        Gate::authorize('update', Blog::class);
        $blog = Blog::findOrFail($blog);
        $request->validate(['tag_id' => 'required|exists:tags,id']);
        $blog->tags()->attach($request->tag_id);

        return back()->with('status', 'Tag attached.');
    }

    /**
     * Remove a tag from blog (admin only).
     */
    public function removeTag(Request $request, $blog)
    {
        Gate::authorize('update', Blog::class);
        $blog = Blog::findOrFail($blog);
        $request->validate(['tag_id' => 'required|exists:tags,id']);
        $blog->tags()->detach($request->tag_id);

        return back()->with('status', 'Tag removed.');
    }
}
