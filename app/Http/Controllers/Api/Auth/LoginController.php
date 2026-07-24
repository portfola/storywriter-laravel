<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\Analytics;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginRequest $request)
    {
        // Matched case-insensitively: emails are stored lowercased at
        // registration, but older accounts may be stored with mixed case.
        $user = User::whereRaw('LOWER(email) = ?', [Str::lower((string) $request->email)])->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The credentials you provided are incorrect.'],
            ]);
        }

        $token = $user->createToken('laravel_api_token')->plainTextToken;

        Analytics::capture((string) $user->id, 'login_completed');

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], Response::HTTP_OK);
    }
}
