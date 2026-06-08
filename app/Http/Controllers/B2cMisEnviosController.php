<?php

namespace App\Http\Controllers\B2C;

use App\Http\Controllers\Controller;

class B2cDashboardController extends Controller
{
    public function index()
    {
        return view('b2c.dashboard');
    }
}