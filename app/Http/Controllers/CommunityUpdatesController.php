<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CommunityUpdatesController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('view-community-updates');

        return view('community-updates.index');
    }
}
