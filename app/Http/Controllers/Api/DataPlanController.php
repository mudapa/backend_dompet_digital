<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\TransactionType;
use App\Models\PaymentMethod;
use App\Models\DataPlan;
use App\Models\DataPlanHistory;
use App\Models\Wallet;
use App\Models\Transaction;


class DataPlanController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data_plan_id' => 'required|integer',
            'phone_number' => 'required|string',
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

        $userId = auth()->user()->id;

        $transactionType = TransactionType::where('code', 'internet')->first();

        $paymentMethod = PaymentMethod::where('code', 'domgi')->first();

        $userWallet = Wallet::where('user_id', $userId)->first();

        $dataPlan = DataPlan::find($request->data_plan_id);

        if (!$dataPlan) {
            return response()->json(
                [
                    'message' => 'Data plan not found',
                ],
                // 404 means not found
                404
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

        if ($userWallet->balance < $dataPlan->price) {
            return response()->json(
                [
                    'message' => 'Your balance is not enough',
                ],
                // 400 means bad request
                400
            );
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'user_id' => $userId,
                'transaction_type_id' => $transactionType->id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $dataPlan->price,
                'transaction_code' => strtoupper(Str::random(10)),
                'description' => 'Internet Data Plan ' . $dataPlan->name,
                'status' => 'success',
            ]);

            DataPlanHistory::create([
                'data_plan_id' => $request->data_plan_id,
                'transaction_id' => $transaction->id,
                'phone_number' => $request->phone_number,
            ]);

            $userWallet->decrement('balance', $dataPlan->price);

            DB::commit();

            return response()->json(
                [
                    'message' => 'Buy Data Plan Success',
                ],
                // 200 means success
                200
            );
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
