<?php

// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function heartbeat(Request $request)
    {
        // Log "Time on App" here (e.g., update a 'last_seen_at' column)
        $request->user()->update(['last_seen_at' => now()]);

        return response()->noContent();
    }
}
