<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminControlPanelController extends Controller
{
    public function index()
    {
        // Logic for displaying the admin control panel
        return view('admin.control-panel.index');
    }
}
