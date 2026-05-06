<?php

namespace App\Http\Controllers\Api\Heirloom\V1;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    // public function index()
    // {
    //     return view('heirloom.dashboard');
    // }

    public function index()
    {
        return response()->json(['message' => 'Heirloom dashboard']);
    }
}