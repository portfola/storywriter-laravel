<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Support\Analytics;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'terms_accepted_at' => now(),
        ]);

        $token = $user->createToken('laravel_api_token')->plainTextToken;

        Analytics::capture((string) $user->id, 'registration_completed');

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], Response::HTTP_CREATED);
    }
}
