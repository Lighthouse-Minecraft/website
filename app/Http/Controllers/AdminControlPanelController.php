<?php

namespace App\Http\Controllers;

class AdminControlPanelController extends Controller
{
    public function index()
    {
        // Logic for displaying the admin control panel
        return view('admin.control-panel.index');
    }
}
