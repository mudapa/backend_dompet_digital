<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;


class TopUpController extends Controller
{
    public function store(Request $request)
    {
        // Validate data
        $data = $request->only('amount', 'pin', 'payment_method_code');

        $validator = Validator::make($data, [
            'amount' => 'required|integer|min:10000',
            'pin' => 'required|digits:6',
            'payment_method_code' => 'required|in:bni_va,bca_va,bri_va',
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

        // Check pin
        $pinChecker = pinChecker($request->pin);

        if (!$pinChecker) {
            return response()->json(
                [
                    'message' => 'Your pin is wrong',
                ],
                // 401 means unauthorized
                401
            );
        }

        // transaction
        $transactionType = TransactionType::where('code', 'top_up')->first();
        // payment method
        $paymentMethod = PaymentMethod::where('code', $request->payment_method_code)->first();

        // Start transaction
        DB::beginTransaction();

        // Create transaction
        try {
            // create transaction
            $transaction = Transaction::create([
                'user_id' => auth()->user()->id,
                'payment_method_id' => $paymentMethod->id,
                'transaction_type_id' => $transactionType->id,
                'amount' => $request->amount,
                'transaction_code' => strtoupper(Str::random(10)),
                'description' => 'Top up via ' . $paymentMethod->name,
                'status' => 'pending',
            ]);

            // call to midtrans
            DB::commit();
        } catch (\Throwable $th) {
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
}
