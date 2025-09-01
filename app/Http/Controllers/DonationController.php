<?php

namespace App\Http\Controllers;

class DonationController extends Controller
{
    public function index()
    {
        return view('donation.index');
    }
}
