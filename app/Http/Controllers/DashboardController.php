<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; 


class DashboardController extends Controller
{
    public function index()
    {
        
        // 1. Security Check: Block regular users
        // (Make sure you ran the 'is_admin' migration we discussed!)
        // if (!auth()->user()->is_admin) {
        //     abort(403, 'Access Denied: Admins Only.');
        // }
        if (auth()->user()->email !== 'timothybenjaminbeckett@gmail.com') { 
            abort(403, 'Access Denied: Admins Only');
        }

        // 2. Fetch Data: Get users and their device tokens
        $users = User::with('tokens')->latest()->paginate(20);

        // 3. Return View: Send the data to the dashboard page
        return view('dashboard', ['users' => $users]);
    }
}
