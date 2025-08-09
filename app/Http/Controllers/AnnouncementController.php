<?php

namespace App\Http\Controllers;

use App\Models\{Announcement};
use Illuminate\Http\{Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Foundation\Auth\Access\{AuthorizesRequests};

class AnnouncementController extends Controller
{
    use AuthorizesRequests;

    public function show($id)
    {
        $announcement = Announcement::with(['author.roles'])->findOrFail($id);

        return view('livewire.announcements.show', compact('announcement'));
    }

    public function create()
    {
        Gate::authorize('create', Announcement::class);
        return view('livewire.announcements.create');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Announcement::class);
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_published' => 'boolean',
        ]);

        $announcement = Announcement::create([
            'title' => $request->title,
            'content' => $request->content,
            'is_published' => $request->is_published,
            'author_id' => Auth::id(),
        ]);
        return view('livewire.announcements.show', ['announcement' => $announcement])
            ->with('status', 'Announcement created successfully!');
    }

    public function edit($id)
    {
        $announcement = Announcement::with(['author.roles'])->findOrFail($id);

        Gate::authorize('update', $announcement);

        return view('livewire.announcements.edit', compact('announcement'));
    }

    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);

        Gate::authorize('update', $announcement);

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_published' => 'boolean',
        ]);

        $announcement->update([
            'title' => $request->title,
            'content' => $request->content,
            'is_published' => $request->is_published,
        ]);

        return view('livewire.announcements.show', ['announcement' => $announcement])
            ->with('status', 'Announcement updated successfully!');
    }

    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);

        Gate::authorize('delete', $announcement);

        $announcement->delete();

        return redirect()->route('acp.index', ['tab' => 'announcement-manager'])
            ->with('status', 'Announcement deleted successfully!');
    }
}
