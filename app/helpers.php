<?php

use App\Models\User;
use App\Models\Wallet;
use Melihovv\Base64ImageDecoder\Base64ImageDecoder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

function getUser($param)
{
    $user = User::where('id', $param)
        ->orWhere('email', $param)
        ->orWhere('username', $param)
        ->first();

    $wallet = Wallet::where('user_id', $user->id)->first();
    $user->profile_picture = $user->profile_picture ? url('storage/' . $user->profile_picture) : "";
    $user->ktp = $user->ktp ? url('storage/' . $user->ktp) : "";
    $user->balance = $wallet->balance;
    $user->card_number = $wallet->card_number;
    $user->pin = $wallet->pin;

    return $user;
}

function pinChecker($pin)
{
    $userId = auth()->user()->id;
    $wallet = Wallet::where('user_id', $userId)->first();

    // check wallet
    if (!$wallet) return false;

    // check pin
    if ($wallet->pin == $pin) return true;
    return false;
}

// upload base64 image
function uploadBase64Image($base64Image)
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
