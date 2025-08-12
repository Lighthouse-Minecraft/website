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
        if (Gate::denies('viewAny', Meeting::class)) {
            abort(404);
        }

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
        Gate::authorize('update', $meeting);

        return view('meeting.edit', compact('meeting'));
    }
}
