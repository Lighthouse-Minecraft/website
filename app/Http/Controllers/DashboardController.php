<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function show($type, $id)
    {
        $allTags = Tag::all();
        $allCategories = Category::all();

        if ($type === 'announcement') {
            $item = Announcement::with(['tags', 'categories'])->findOrFail($id);

            return view('livewire.announcements.show', [
                'announcement' => $item,
                'allTags' => $allTags,
                'allCategories' => $allCategories,
            ]);
        } elseif ($type === 'blog') {
            $item = Blog::with(['tags', 'categories'])->findOrFail($id);

            return view('livewire.blogs.show', [
                'blog' => $item,
                'allTags' => $allTags,
                'allCategories' => $allCategories,
            ]);
        } else {
            abort(404);
        }
    }

    public function readyRoom()
    {
        Gate::authorize('view-ready-room');

        return view('dashboard.ready-room');
    }
}
