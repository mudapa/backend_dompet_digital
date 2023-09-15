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

            // build midtrans parameters
            $params = $this->buildMidtransParameters([
                'transaction_code' => $transaction->transaction_code,
                'amount' => $transaction->amount,
                'payment_method' => $paymentMethod->code,
            ]);

            // call to midtrans
            $midtrans = $this->callMidtrans($params);

            // update transaction
            DB::commit();

            // return response
            return response()->json($midtrans);
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

    // call to midtrans
    private function callMidtrans(array $params)
    {
        // Set your Merchant Server Key
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        \Midtrans\Config::$isProduction = (bool) env('MIDTRANS_IS_PRODUCTION');
        // Set sanitization on (default)
        \Midtrans\Config::$isSanitized = (bool) env('MIDTRANS_IS_SANITIZED');
        // Set 3DS transaction for credit card to true
        \Midtrans\Config::$is3ds = (bool) env('MIDTRANS_IS_3DS');

        // Call Snap API (https://snap-docs.midtrans.com)
        $createTransaction = \Midtrans\Snap::createTransaction($params);

        // return url or token
        return [
            'redirect_url' => $createTransaction->redirect_url,
            'token' => $createTransaction->token,
        ];
    }

    // build midtrans parameters
    private function buildMidtransParameters(array $params)
    {
        $transactionDetails = [
            'order_id' => $params['transaction_code'],
            'gross_amount' => $params['amount'],
        ];

        $user = auth()->user();

        // split name
        $spliName = $this->splitName($user->name);

        // customer details
        $customerDetails = [
            'first_name' => $spliName['first_name'],
            'last_name' => $spliName['last_name'],
            'email' => $user->email,
            'phone' => $user->phone_number,
        ];

        // enabled payments
        $enabledPayment = [
            $params['payment_method']
        ];

        // return midtrans parameters
        return [
            'transaction_details' => $transactionDetails,
            'customer_details' => $customerDetails,
            'enabled_payments' => $enabledPayment,
        ];
    }

    // split name
    private function splitName($fullName)
    {

        $name = explode(' ', $fullName);

        // case
        // 'yuki saputra kurnia'
        // ['yuki', 'saputra', 'kurnia']

        $lastName = count($name) > 1 ? array_pop($name) : $fullName; //kurnia
        $firstName = implode(' ', $name); //yuki saputra

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }
}
