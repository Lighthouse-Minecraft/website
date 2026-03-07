<?php

namespace App\Http\Controllers;

class AdminControlPanelController extends Controller
{
    public function index()
    {
        \Illuminate\Support\Facades\Gate::authorize('view-acp');

        return view('admin.control-panel.index');
    }
}
