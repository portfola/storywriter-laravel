<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest; 
use App\Models\User; 
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first(); 

        /**
         * Handle the incoming request.
         */
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The credentials you provided are incorrect.']
            ]);
        }

        $token = $user->createToken('laravel_api_token')->plainTextToken; 

        return response()->json([
            'token' => $token, 
            'user' => $user
        ], Response::HTTP_OK);
    }
}
