<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function readyRoom()
    {
        Gate::authorize('view-ready-room');

        return view('dashboard.ready-room');
    }
}
