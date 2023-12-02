<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Wallet;
use Melihovv\Base64ImageDecoder\Base64ImageDecoder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    // register
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

        // Start transaction
        DB::beginTransaction();
        // Create user
        try {
            $profilePicture = null;
            if ($request->profile_picture) {
                $profilePicture = uploadBase64Image($request->profile_picture);
            }

            $ktp = null;
            if ($request->ktp) {
                $ktp = uploadBase64Image($request->ktp);
            }

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $request->email,
                'password' => bcrypt($request->password),
                'profile_picture' => $profilePicture,
                'ktp' => $ktp,
                'verified' => ($ktp) ? true : false,
            ]);

            // Create wallet
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'pin' => $request->pin,
                'card_number' => $this->generateCardNumber(16),
            ]);

            // Commit transaction
            DB::commit();

            // Get user data
            $token = JWTAuth::attempt([
                'email' => $request->email,
                'password' => $request->password,
            ]);

            // get user data
            $userResponse = getUser($request->email);
            // get token
            $userResponse->token = $token;
            // get token expiration
            $userResponse->token_expiration = JWTAuth::factory()->getTTL() * 60;
            // token type
            $userResponse->token_type = 'bearer';

            return response()->json(
                $userResponse
            );
        } catch (\Throwable $th) {
            // Rollback transaction
            DB::rollBack();
            return response()->json(
                [
                    'message' => $th->getMessage(),
                ],
                // 500 means internal server error
                500
            );
        }
    }

    // login
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        $validator = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // check if validator fails
        if ($validator->fails()) {
            return response()->json(
                [
                    'error' => $validator->errors(),
                ],
                // 400 means bad request
                400
            );
        }

        try {
            // check if credentials is valid
            $token = JWTAuth::attempt($credentials);

            // check if token is not valid
            if (!$token) {
                return response()->json(
                    [
                        'message' => 'Login credentials are invalid',
                    ],
                    // 401 means unauthorized
                    401
                );
            }

            // get user data
            $userResponse = getUser($request->email);
            // get token
            $userResponse->token = $token;
            // get token expiration
            $userResponse->token_expiration = JWTAuth::factory()->getTTL() * 60;
            // token type
            $userResponse->token_type = 'bearer';

            return response()->json(
                $userResponse
            );
        } catch (JWTException $th) {
            return response()->json(
                [
                    'message' => $th->getMessage(),
                ],
                // 500 means internal server error
                500
            );
        }
    }

    // generate card number
    private function generateCardNumber($length)
    {
        // Generate random number
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= mt_rand(0, 9);
        }

        // Check if card number already exists
        $wallet = Wallet::where('card_number', $result)->exists();

        if ($wallet) {
            // Generate again if card number already exists
            return $this->generateCardNumber($length);
        }

        // Return card number
        return $result;
    }

    // upload base64 image
    private function uploadBase64Image($base64Image)
    {
        // Decode base64 image
        $decoder = new Base64ImageDecoder($base64Image, $allowedFormats = ['jpeg', 'png', 'gif']);
        // Get decoded image
        $decodedContent = $decoder->getDecodedContent();
        // Get image format
        $format = $decoder->getFormat();
        // Generate random image name
        $image = Str::random(10) . '.' . $format;
        // Upload image to storage
        Storage::disk('public')->put($image, $decodedContent);

        // Return image name
        return $image;
    }

    public function logout()
    {
        auth()->logout();

        return response()->json([
            'message' => 'User logged out successfully',
        ]);
    }
}
