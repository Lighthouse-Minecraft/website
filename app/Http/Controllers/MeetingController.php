<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Support\Facades\Gate;

class MeetingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', Meeting::class);

        return view('meeting.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Meeting $meeting)
    {
        Gate::authorize('view', $meeting);

        return view('meeting.show', compact('meeting'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Meeting $meeting)
    {
        // The edit page got turned into the main meeting page...
        Gate::authorize('view', $meeting);

        return view('meeting.edit', compact('meeting'));
    }
}
