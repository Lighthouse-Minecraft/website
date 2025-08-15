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
                    ->orWhere('body', 'like', "%$search%");
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
        $blog = Blog::with(['author.roles'])
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->firstOrFail();

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
            'tags' => 'array',
            'categories' => 'array',
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
            'categories' => $validated['categories'] ?? [],
            'tags' => $validated['tags'] ?? [],
            'author_id' => $validated['author_id'] ?? null,
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? now() : null,
            'is_public' => $request->boolean('is_public', false),
        ]);

        // Attach tags and categories if provided
        if (! empty($validated['tags'])) {
            $blog->tags()->attach($validated['tags']);
        }
        if (! empty($validated['categories'])) {
            $blog->categories()->attach($validated['categories']);
        }

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

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required',
            'author_id' => 'nullable|exists:users,id',
            'tags' => 'array',
            'categories' => 'array',
            'published' => 'boolean',
            'published_at' => 'nullable|date',
            'is_public' => 'boolean',
        ]);

        $blog->update([
            'title' => $request->title,
            'content' => $request->content,
            'author_id' => $request->author_id,
            'tags' => $request->tags,
            'categories' => $request->categories,
            'published' => $request->published,
            'published_at' => $request->published_at,
            'is_public' => $request->boolean('is_public', false),
        ]);

        return response()->view('blogs.show', [
            'blog' => $blog,
            'status' => 'Blog updated successfully!',
        ], 200);
    }

    /**
     * Remove the specified blog from storage.
     */
    public function destroy($id)
    {
        $blog = Blog::findOrFail($id);

        Gate::authorize('delete', $blog);

        $blog->delete();

        return response()->view('blogs.show', [
            'blog' => $blog,
            'status' => 'Blog deleted successfully!',
        ], 200);
    }
}
