<?php

// app/Http/Controllers/Api/AuthController.php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            // 'password' => 'required', // Optional if you are just capturing info, see note below
            'device_name' => 'required',
        ]);

        // "Sign Up or Sign In" logic (Simplified for data collection)
        $user = User::firstOrCreate(
            ['email' => $request->email],
            ['name' => $request->name, 'password' => Hash::make($request->password ?? 'default_password')]
        );

        // Create the token
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }
    
    public function heartbeat(Request $request)
    {
        // Log "Time on App" here (e.g., update a 'last_seen_at' column)
        $request->user()->update(['last_seen_at' => now()]);
        return response()->noContent();
    }
}