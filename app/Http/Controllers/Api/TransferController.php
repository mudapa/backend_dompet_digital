<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\TransferHistory;
use App\Models\TransactionType;
use App\Models\Wallet;
use App\Models\User;
use App\Models\PaymentMethod;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->only('amount', 'pin', 'send_to');

        $validator = Validator::make($data, [
            'amount' => 'required|integer|min:10000',
            'pin' => 'required|digits:6',
            'send_to' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'error' => $validator->errors(),
                ],
                400
            );
        }

        // sender
        $sender = auth()->user();
        // receiver
        $receiver = User::select('users.id', 'users.username')
            ->join('wallets', 'wallets.user_id', 'users.id')
            ->where('users.username', $request->send_to)
            ->orWhere('wallets.card_number', $request->send_to)
            ->first();

        // check pin
        $pinChecker = pinChecker($request->pin);
        if (!$pinChecker) {
            return response()->json(
                [
                    'message' => 'Your pin is wrong',
                ],
                // 400 means bad request
                400
            );
        }

        // check receiver
        if (!$receiver) {
            return response()->json(
                [
                    'message' => 'Receiver not found',
                ],
                // 404 means not found
                404
            );
        }

        // check transfer sender equal to sender
        if ($sender->username == $receiver->username) {
            return response()->json(
                [
                    'message' => 'You cannot transfer to yourself',
                ],
                // 400 means bad request
                400
            );
        }

        // check balance transfer
        $senderWallet = Wallet::where('user_id', $sender->id)->first();

        if ($senderWallet->balance < $request->amount) {
            return response()->json(
                [
                    'message' => 'Your balance is not enough',
                ],
                // 400 means bad request
                400
            );
        }

        // start transaction
        DB::beginTransaction();

        try {
            $transactionType = TransactionType::whereIn('code', ['receive', 'transfer'])
                ->orderBy('code', 'asc')
                ->get();

            $receiveTransactionType = $transactionType->first();
            $transferTransactionType = $transactionType->last();

            $transactionCode = strtoupper(Str::random(10));
            $paymentMethod = PaymentMethod::where('code', 'domgi')->first();

            // create transaction for transfer
            $transferTransaction = Transaction::create([
                'user_id' => $sender->id,
                'transaction_type_id' => $transferTransactionType->id,
                'description' => 'Transfer funs to ' . $receiver->username,
                'amount' => $request->amount,
                'transaction_code' => $transactionCode,
                'status' => 'success',
                'payment_method_id' => $paymentMethod->id,
            ]);

            $senderWallet->decrement('balance', $request->amount);

            // create transaction for receive
            $receiveTransaction = Transaction::create([
                'user_id' => $receiver->id,
                'transaction_type_id' => $receiveTransactionType->id,
                'description' => 'Receive funs from ' . $sender->username,
                'amount' => $request->amount,
                'transaction_code' => $transactionCode,
                'status' => 'success',
                'payment_method_id' => $paymentMethod->id,
            ]);

            Wallet::where('user_id', $receiver->id)->increment('balance', $request->amount);

            TransferHistory::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'transaction_code' => $transactionCode,
            ]);

            DB::commit();
            return response()->json(
                [
                    'message' => 'Transfer success',
                ],
                // 200 means success
                200
            );
        } catch (\Throwable $th) {
            DB::rollback();
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
