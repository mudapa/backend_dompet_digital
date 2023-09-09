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

        // Create user
        try {
            $profilePicture = null;
            $ktp = null;

            if ($request->profile_picture) {
                $profilePicture = $this->uploadBase64Image($request->profile_picture);
            }

            if ($request->ktp) {
                $ktp = $this->uploadBase64Image($request->ktp);
            }
        } catch (\Throwable $th) {
            echo $th;
        }
    }

    // Upload base64 image
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

        return $image;
    }
}
