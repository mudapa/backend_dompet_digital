<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Wallet;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validate data
        $data = $request->all();

        $validator = Validator::make($data, [
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'pin' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'error' => $validator->errors(),
                ],
                // 400 means bad request
                400
            );
        }

        // Check if email already exists
        $user = User::where('email', $request->email)->exists();

        if ($user) {
            return response()->json(
                [
                    'message' => 'Email already taken',
                ],
                // 409 means conflict
                409
            );
        }
    }
}
