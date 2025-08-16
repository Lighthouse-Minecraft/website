<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TaxonomyController extends Controller
{
    /**
     * Category methods
     */
    public function categoriesIndex(Request $request)
    {
        Gate::authorize('viewAny', Category::class);
        $categories = Category::latest()->paginate(20);

        return view('taxonomy.categories.index', compact('categories'));
    }

    public function categoriesShow($id)
    {
        $category = Category::findOrFail($id);
        Gate::authorize('view', $category);

        return view('taxonomy.categories.show', compact('category'));
    }

    public function categoriesStore(Request $request)
    {
        Gate::authorize('create', Category::class);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'color' => 'nullable|string',
            'author_id' => 'nullable|exists:users,id',
        ]);
        $validated['slug'] = Str::slug($validated['name']);
        $category = Category::create($validated);

        return response()->view('taxonomy.categories.show', [
            'category' => $category,
            'status' => 'Category created successfully!',
        ], 201);
    }

    public function categoriesUpdate(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        Gate::authorize('update', $category);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,'.$id,
            'description' => 'nullable|string',
            'color' => 'nullable|string',
        ]);
        $category->update($validated);

        return redirect()->back()->with('status', 'Category updated successfully!');
    }

    public function categoriesDestroy($id)
    {
        $category = Category::findOrFail($id);
        Gate::authorize('delete', $category);
        $category->delete();

        return redirect()->back()->with('status', 'Category deleted successfully!');
    }

    /**
     * Tag methods
     */
    public function tagsIndex(Request $request)
    {
        Gate::authorize('viewAny', Tag::class);
        $tags = Tag::latest()->paginate(20);

        return view('taxonomy.tags.index', compact('tags'));
    }

    public function tagsShow($id)
    {
        $tag = Tag::findOrFail($id);
        Gate::authorize('view', $tag);

        return view('taxonomy.tags.show', compact('tag'));
    }

    public function tagsStore(Request $request)
    {
        Gate::authorize('create', Tag::class);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name',
            'description' => 'nullable|string',
            'color' => 'nullable|string',
            'author_id' => 'nullable|exists:users,id',
        ]);
        $validated['slug'] = Str::slug($validated['name']);
        $tag = Tag::create($validated);

        return response()->view('taxonomy.tags.show', [
            'tag' => $tag,
            'status' => 'Tag created successfully!',
        ], 201);
    }

    public function tagsUpdate(Request $request, $id)
    {
        $tag = Tag::findOrFail($id);
        Gate::authorize('update', $tag);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name,'.$id,
            'description' => 'nullable|string',
            'color' => 'nullable|string',
        ]);
        $tag->update($validated);

        return redirect()->back()->with('status', 'Tag updated successfully!');
    }

    public function tagsDestroy($id)
    {
        $tag = Tag::findOrFail($id);
        Gate::authorize('delete', $tag);
        $tag->delete();

        return redirect()->back()->with('status', 'Tag deleted successfully!');
    }
}
